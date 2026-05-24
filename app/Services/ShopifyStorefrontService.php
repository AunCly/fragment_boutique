<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ShopifyStorefrontService
{
    private string $endpoint;

    public function __construct()
    {
        $domain = config('services.shopify.store_domain');
        $this->endpoint = "https://{$domain}/api/2024-10/graphql.json";
    }

    /**
     * @return array<int, array{id: string, title: string, price: string, image: string|null, variantId: string}>
     *
     * @throws ConnectionException
     */
    public function fetchProducts(): array
    {
        $query = <<<'GRAPHQL'
        {
            products(first: 20) {
                edges {
                    node {
                        id
                        title
                        variants(first: 1) {
                            edges {
                                node {
                                    id
                                    price {
                                        amount
                                        currencyCode
                                    }
                                }
                            }
                        }
                        featuredImage {
                            url
                            altText
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $response = Http::withHeaders([
            'X-Shopify-Storefront-Access-Token' => config('services.shopify.storefront_token'),
            'Content-Type' => 'application/json',
        ])->post($this->endpoint, ['query' => $query]);

        $products = [];

        foreach ($response->json('data.products.edges', []) as $edge) {
            $node = $edge['node'];
            $variant = $node['variants']['edges'][0]['node'] ?? null;

            if (! $variant) {
                continue;
            }

            $products[] = [
                'id' => $node['id'],
                'title' => $node['title'],
                'price' => $variant['price']['amount'],
                'currency' => $variant['price']['currencyCode'],
                'image' => $node['featuredImage']['url'] ?? null,
                'imageAlt' => $node['featuredImage']['altText'] ?? $node['title'],
                'variantId' => $variant['id'],
            ];
        }

        return $products;
    }
}
