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
}
