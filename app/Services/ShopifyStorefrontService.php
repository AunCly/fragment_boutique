<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
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
     * @return array<int, array{id: string, title: string, price: string, image: string|null, variantId: string, category: string, tags: array<string, string>}>
     *
     * @throws ConnectionException
     */
    public function fetchProducts(int $first = 20): array
    {
        return Cache::flexible("shopify_products_{$first}", [300, 3600], function () use ($first) {
            $query = <<<GRAPHQL
            {
                products(first: {$first}) {
                    edges {
                        node {
                            id
                            title
                            productType
                            tags
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
                    'category' => $node['productType'] ?: 'UNDEFINED',
                    'tags' => $this->parseTags($node['tags'] ?? []),
                ];
            }

            return $products;
        });
    }

    /**
     * @return array{id: string, title: string, category: string, description: string, images: array<int, array{url: string, alt: string}>, options: array<int, array{name: string, values: array<int, string>}>, variants: array<int, array{id: string, price: string, currency: string, selectedOptions: array<string, string>}>}|null
     *
     * @throws ConnectionException
     */
    public function fetchProduct(string $id): ?array
    {
        return Cache::flexible("shopify_product_{$id}", [300, 3600], function () use ($id) {
            $query = <<<'GRAPHQL'
            query ProductById($id: ID!) {
                product(id: $id) {
                    id
                    title
                    productType
                    tags
                    descriptionHtml
                    images(first: 10) {
                        edges {
                            node {
                                url
                                altText
                            }
                        }
                    }
                    options {
                        name
                        values
                    }
                    variants(first: 100) {
                        edges {
                            node {
                                id
                                price {
                                    amount
                                    currencyCode
                                }
                                selectedOptions {
                                    name
                                    value
                                }
                            }
                        }
                    }
                }
            }
            GRAPHQL;

            $response = Http::withHeaders([
                'X-Shopify-Storefront-Access-Token' => config('services.shopify.storefront_token'),
                'Content-Type' => 'application/json',
            ])->post($this->endpoint, [
                'query' => $query,
                'variables' => ['id' => "gid://shopify/Product/{$id}"],
            ]);

            $node = $response->json('data.product');

            if (! $node) {
                return null;
            }

            return [
                'id' => $node['id'],
                'title' => $node['title'],
                'category' => implode(' · ', $this->tagValues($node['tags'] ?? [], 'Catégorie')) ?: ($node['productType'] ?: 'UNDEFINED'),
                'description' => $node['descriptionHtml'] ?? '',
                'images' => collect($node['images']['edges'] ?? [])
                    ->map(fn (array $edge) => [
                        'url' => $edge['node']['url'],
                        'alt' => $edge['node']['altText'] ?? $node['title'],
                    ])
                    ->values()
                    ->all(),
                'options' => collect($node['options'] ?? [])
                    ->map(fn (array $option) => [
                        'name' => $option['name'],
                        'values' => $option['values'],
                    ])
                    ->values()
                    ->all(),
                'variants' => collect($node['variants']['edges'] ?? [])
                    ->map(fn (array $edge) => [
                        'id' => $edge['node']['id'],
                        'price' => $edge['node']['price']['amount'],
                        'currency' => $edge['node']['price']['currencyCode'],
                        'selectedOptions' => collect($edge['node']['selectedOptions'] ?? [])
                            ->mapWithKeys(fn (array $option) => [$option['name'] => $option['value']])
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ];
        });
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<string, string>
     */
    private function parseTags(array $tags): array
    {
        $parsed = [];

        foreach ($tags as $tag) {
            if (! str_contains($tag, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $tag, 2);
            $parsed[trim($name)] = trim($value);
        }

        return $parsed;
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<int, string>
     */
    private function tagValues(array $tags, string $name): array
    {
        $values = [];

        foreach ($tags as $tag) {
            if (! str_contains($tag, ':')) {
                continue;
            }

            [$tagName, $value] = explode(':', $tag, 2);

            if (trim($tagName) === $name) {
                $values[] = trim($value);
            }
        }

        return $values;
    }
}
