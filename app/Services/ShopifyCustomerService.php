<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopifyCustomerService
{
    private string $endpoint;

    private string $storefrontToken;

    public function __construct()
    {
        $domain = config('services.shopify.store_domain');
        $this->endpoint = "https://{$domain}/api/2024-10/graphql.json";
        $this->storefrontToken = config('services.shopify.storefront_token');
    }

    private function graphql(string $query, array $variables = []): array
    {
        $payload = ['query' => $query];
        if ($variables) {
            $payload['variables'] = $variables;
        }

        return Http::withHeaders([
            'X-Shopify-Storefront-Access-Token' => $this->storefrontToken,
            'Content-Type' => 'application/json',
        ])->post($this->endpoint, $payload)->json();
    }

    /**
     * @return array{token: string, expiresAt: string}
     *
     * @throws RuntimeException
     */
    public function createCustomer(string $firstName, string $lastName, string $email, string $password): array
    {
        $mutation = <<<'GRAPHQL'
        mutation customerCreate($input: CustomerCreateInput!) {
            customerCreate(input: $input) {
                customer { id }
                customerUserErrors { code field message }
            }
        }
        GRAPHQL;

        $result = $this->graphql($mutation, [
            'input' => compact('firstName', 'lastName', 'email', 'password'),
        ]);

        $errors = $result['data']['customerCreate']['customerUserErrors'] ?? [];
        if ($errors) {
            throw new RuntimeException($this->translateError($errors[0]));
        }

        return $this->createAccessToken($email, $password);
    }

    /**
     * @return array{token: string, expiresAt: string}
     *
     * @throws RuntimeException
     */
    public function createAccessToken(string $email, string $password): array
    {
        $mutation = <<<'GRAPHQL'
        mutation customerAccessTokenCreate($input: CustomerAccessTokenCreateInput!) {
            customerAccessTokenCreate(input: $input) {
                customerAccessToken { accessToken expiresAt }
                customerUserErrors { code field message }
            }
        }
        GRAPHQL;

        $result = $this->graphql($mutation, ['input' => compact('email', 'password')]);

        $errors = $result['data']['customerAccessTokenCreate']['customerUserErrors'] ?? [];
        if ($errors) {
            throw new RuntimeException($this->translateError($errors[0]));
        }

        $tokenData = $result['data']['customerAccessTokenCreate']['customerAccessToken'] ?? null;
        if (! $tokenData) {
            throw new RuntimeException('Identifiants incorrects.');
        }

        return ['token' => $tokenData['accessToken'], 'expiresAt' => $tokenData['expiresAt']];
    }

    public function deleteAccessToken(string $customerAccessToken): void
    {
        $mutation = <<<'GRAPHQL'
        mutation customerAccessTokenDelete($customerAccessToken: String!) {
            customerAccessTokenDelete(customerAccessToken: $customerAccessToken) {
                deletedAccessToken
                userErrors { field message }
            }
        }
        GRAPHQL;

        $this->graphql($mutation, compact('customerAccessToken'));
    }

    /**
     * @throws RuntimeException
     */
    public function sendPasswordReset(string $email): void
    {
        $mutation = <<<'GRAPHQL'
        mutation customerRecover($email: String!) {
            customerRecover(email: $email) {
                customerUserErrors { code field message }
            }
        }
        GRAPHQL;

        $result = $this->graphql($mutation, compact('email'));

        $errors = $result['data']['customerRecover']['customerUserErrors'] ?? [];
        if ($errors) {
            throw new RuntimeException($this->translateError($errors[0]));
        }
    }

    /**
     * @return array{id: string, firstName: string, lastName: string, email: string, defaultAddress: array<string, string>|null, orders: array<int, array<string, mixed>>}
     *
     * @throws RuntimeException
     */
    public function getCustomer(string $customerAccessToken): array
    {
        $query = <<<'GRAPHQL'
        query getCustomer($customerAccessToken: String!) {
            customer(customerAccessToken: $customerAccessToken) {
                id
                firstName
                lastName
                email
                defaultAddress {
                    id
                    firstName
                    lastName
                    address1
                    address2
                    city
                    province
                    zip
                    country
                    phone
                }
                orders(first: 20, sortKey: PROCESSED_AT, reverse: true) {
                    edges {
                        node {
                            id
                            orderNumber
                            processedAt
                            fulfillmentStatus
                            financialStatus
                            statusUrl
                            totalPrice { amount currencyCode }
                            subtotalPrice { amount currencyCode }
                            totalShippingPrice { amount currencyCode }
                            totalTax { amount currencyCode }
                            lineItems(first: 50) {
                                edges {
                                    node {
                                        title
                                        quantity
                                        variant {
                                            price { amount currencyCode }
                                            image { url altText }
                                        }
                                    }
                                }
                            }
                            successfulFulfillments {
                                trackingInfo { number url }
                            }
                            shippingAddress {
                                firstName
                                lastName
                                address1
                                address2
                                city
                                province
                                zip
                                country
                                phone
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $result = $this->graphql($query, compact('customerAccessToken'));
        $customer = $result['data']['customer'] ?? null;

        if (! $customer) {
            throw new RuntimeException('Session invalide.');
        }

        $orders = [];
        foreach ($customer['orders']['edges'] ?? [] as $edge) {
            $node = $edge['node'];

            $lineItems = array_map(fn ($e) => [
                'title' => $e['node']['title'],
                'quantity' => $e['node']['quantity'],
                'price' => $e['node']['variant']['price'] ?? null,
                'image' => $e['node']['variant']['image'] ?? null,
            ], $node['lineItems']['edges'] ?? []);

            $trackingInfo = [];
            foreach ($node['successfulFulfillments'] ?? [] as $fulfillment) {
                foreach ($fulfillment['trackingInfo'] ?? [] as $info) {
                    if ($info['number']) {
                        $trackingInfo[] = $info;
                    }
                }
            }

            $orders[] = [
                'id' => $node['id'],
                'orderNumber' => $node['orderNumber'],
                'processedAt' => $node['processedAt'],
                'fulfillmentStatus' => $node['fulfillmentStatus'],
                'financialStatus' => $node['financialStatus'],
                'statusUrl' => $node['statusUrl'],
                'totalPrice' => $node['totalPrice'],
                'subtotalPrice' => $node['subtotalPrice'],
                'totalShippingPrice' => $node['totalShippingPrice'],
                'totalTax' => $node['totalTax'],
                'lineItems' => $lineItems,
                'trackingInfo' => $trackingInfo,
                'shippingAddress' => $node['shippingAddress'],
            ];
        }

        return [
            'id' => $customer['id'],
            'firstName' => $customer['firstName'],
            'lastName' => $customer['lastName'],
            'email' => $customer['email'],
            'defaultAddress' => $customer['defaultAddress'],
            'orders' => $orders,
        ];
    }

    /**
     * @param  array{firstName?: string, lastName?: string, email?: string, password?: string}  $data
     *
     * @throws RuntimeException
     */
    public function updateCustomer(string $customerAccessToken, array $data): void
    {
        $mutation = <<<'GRAPHQL'
        mutation customerUpdate($customerAccessToken: String!, $customer: CustomerUpdateInput!) {
            customerUpdate(customerAccessToken: $customerAccessToken, customer: $customer) {
                customer { id }
                customerUserErrors { code field message }
            }
        }
        GRAPHQL;

        $result = $this->graphql($mutation, ['customerAccessToken' => $customerAccessToken, 'customer' => $data]);

        $errors = $result['data']['customerUpdate']['customerUserErrors'] ?? [];
        if ($errors) {
            throw new RuntimeException($this->translateError($errors[0]));
        }
    }

    /**
     * @param  array<string, string|null>  $address
     *
     * @throws RuntimeException
     */
    public function upsertDefaultAddress(string $customerAccessToken, ?string $existingAddressId, array $address): void
    {
        if ($existingAddressId) {
            $this->updateAddress($customerAccessToken, $existingAddressId, $address);
        } else {
            $newId = $this->createAddress($customerAccessToken, $address);
            $this->setDefaultAddress($customerAccessToken, $newId);
        }
    }

    /**
     * @param  array<string, string|null>  $address
     *
     * @throws RuntimeException
     */
    private function updateAddress(string $customerAccessToken, string $id, array $address): void
    {
        $mutation = <<<'GRAPHQL'
        mutation customerAddressUpdate($customerAccessToken: String!, $id: ID!, $address: MailingAddressInput!) {
            customerAddressUpdate(customerAccessToken: $customerAccessToken, id: $id, address: $address) {
                customerAddress { id }
                customerUserErrors { code field message }
            }
        }
        GRAPHQL;

        $result = $this->graphql($mutation, compact('customerAccessToken', 'id', 'address'));

        $errors = $result['data']['customerAddressUpdate']['customerUserErrors'] ?? [];
        if ($errors) {
            throw new RuntimeException($this->translateError($errors[0]));
        }
    }

    /**
     * @param  array<string, string|null>  $address
     *
     * @throws RuntimeException
     */
    private function createAddress(string $customerAccessToken, array $address): string
    {
        $mutation = <<<'GRAPHQL'
        mutation customerAddressCreate($customerAccessToken: String!, $address: MailingAddressInput!) {
            customerAddressCreate(customerAccessToken: $customerAccessToken, address: $address) {
                customerAddress { id }
                customerUserErrors { code field message }
            }
        }
        GRAPHQL;

        $result = $this->graphql($mutation, compact('customerAccessToken', 'address'));

        $errors = $result['data']['customerAddressCreate']['customerUserErrors'] ?? [];
        if ($errors) {
            throw new RuntimeException($this->translateError($errors[0]));
        }

        return $result['data']['customerAddressCreate']['customerAddress']['id'];
    }

    private function setDefaultAddress(string $customerAccessToken, string $addressId): void
    {
        $mutation = <<<'GRAPHQL'
        mutation customerDefaultAddressUpdate($customerAccessToken: String!, $addressId: ID!) {
            customerDefaultAddressUpdate(customerAccessToken: $customerAccessToken, addressId: $addressId) {
                customer { id }
                customerUserErrors { code field message }
            }
        }
        GRAPHQL;

        $this->graphql($mutation, compact('customerAccessToken', 'addressId'));
    }

    /**
     * @param  array{code: string, message: string}  $error
     */
    private function translateError(array $error): string
    {
        return match ($error['code'] ?? '') {
            'TAKEN' => 'Cette adresse email est déjà utilisée.',
            'INVALID' => 'Adresse email invalide.',
            'TOO_SHORT' => 'Le mot de passe est trop court (8 caractères minimum).',
            'UNIDENTIFIED_CUSTOMER' => 'Identifiants incorrects.',
            'CUSTOMER_DISABLED' => 'Ce compte est désactivé.',
            default => $error['message'],
        };
    }
}
