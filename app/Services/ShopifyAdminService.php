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

            $token = $response->json('access_token');

            if (! $token) {
                throw new \RuntimeException("Shopify token exchange failed (HTTP {$response->status()}).");
            }

            return $token;
        });
    }

    /**
     * Line items are Shopify variants only, so prices always come from Shopify and never from the client.
     *
     * @param  array<int, array{type: 'variant', variantId: string, quantity: int}>  $items
     *
     * @throws ConnectionException
     */
    public function createDraftOrder(array $items): string
    {
        $token = $this->getAccessToken();

        $lineItems = array_map(fn (array $item): array => [
            'variant_id' => (int) preg_replace('/.*\//', '', $item['variantId']),
            'quantity' => $item['quantity'],
        ], $items);

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
