<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ShopifyAdminService
{
    private string $storeDomain;

    public function __construct()
    {
        $this->storeDomain = config('services.shopify.store_domain');
    }

    /**
     * @throws ConnectionException
     */
    private function getAccessToken(): string
    {
        return Cache::remember('shopify_admin_access_token', 86399 - 60, function () {
            $response = Http::asForm()->post(
                "https://{$this->storeDomain}/admin/oauth/access_token",
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => config('services.shopify.client_id'),
                    'client_secret' => config('services.shopify.client_secret'),
                ]
            );

            \Log::info('Shopify token response', ['status' => $response->status(), 'body' => $response->json()]);

            $token = $response->json('access_token');

            if (! $token) {
                throw new \RuntimeException('Shopify token exchange failed: '.$response->body());
            }

            return $token;
        });
    }

    /**
     * @param  array<int, array{type: 'variant'|'custom', variantId?: string, title?: string, price?: string|float, quantity: int}>  $items
     *
     * @throws ConnectionException
     */
    public function createDraftOrder(array $items): string
    {
        $token = $this->getAccessToken();

        $lineItems = array_map(function (array $item): array {
            if ($item['type'] === 'variant') {
                $variantId = preg_replace('/.*\//', '', $item['variantId']);

                return [
                    'variant_id' => (int) $variantId,
                    'quantity' => $item['quantity'],
                ];
            }

            return [
                'title' => $item['title'],
                'price' => number_format((float) $item['price'], 2, '.', ''),
                'quantity' => $item['quantity'],
            ];
        }, $items);

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ])->post("https://{$this->storeDomain}/admin/api/2024-10/draft_orders.json", [
            'draft_order' => [
                'line_items' => $lineItems,
                'use_customer_default_address' => false,
            ],
        ]);

        return $response->json('draft_order.invoice_url');
    }
}
