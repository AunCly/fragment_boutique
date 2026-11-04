<?php

namespace Tests\Feature\Shop;

use App\Services\ShopifyAdminService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    private const DRAFT_ORDERS_URL = 'https://test-shop.myshopify.com/admin/api/2024-10/draft_orders.json';

    private const INVOICE_URL = 'https://test-shop.myshopify.com/invoices/abc';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.shopify.store_domain' => 'test-shop.myshopify.com']);
        Cache::put('shopify_admin_access_token', 'test-admin-token');

        Http::fake([
            self::DRAFT_ORDERS_URL => Http::response(['draft_order' => ['invoice_url' => self::INVOICE_URL]]),
        ]);
    }

    public function test_variant_items_create_a_draft_order_priced_by_shopify(): void
    {
        $this->postJson(route('shop.checkout'), [
            'items' => [
                ['type' => 'variant', 'variantId' => 'gid://shopify/ProductVariant/123', 'quantity' => 2, 'price' => 0.01],
            ],
        ])
            ->assertOk()
            ->assertJson(['redirect' => self::INVOICE_URL]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === self::DRAFT_ORDERS_URL
                && $request->hasHeader('X-Shopify-Access-Token', 'test-admin-token')
                && $request['draft_order']['line_items'] === [['variant_id' => 123, 'quantity' => 2]];
        });
    }

    public function test_admin_token_exchange_is_never_logged(): void
    {
        Cache::forget('shopify_admin_access_token');
        Log::spy();
        Http::fake([
            'https://test-shop.myshopify.com/admin/oauth/access_token' => Http::response(['access_token' => 'secret-admin-token']),
            self::DRAFT_ORDERS_URL => Http::response(['draft_order' => ['invoice_url' => self::INVOICE_URL]]),
        ]);

        $this->postJson(route('shop.checkout'), [
            'items' => [['type' => 'variant', 'variantId' => 'gid://shopify/ProductVariant/123', 'quantity' => 1]],
        ])->assertOk();

        Log::shouldNotHaveReceived('info');
        $this->assertSame('secret-admin-token', Cache::get('shopify_admin_access_token'));
    }

    public function test_failed_token_exchange_does_not_expose_the_shopify_response(): void
    {
        Cache::forget('shopify_admin_access_token');
        Http::fake([
            'https://test-shop.myshopify.com/admin/oauth/access_token' => Http::response(['error' => 'invalid_client', 'client_secret' => 'leaked'], 401),
        ]);

        try {
            app(ShopifyAdminService::class)->createDraftOrder([
                ['type' => 'variant', 'variantId' => 'gid://shopify/ProductVariant/123', 'quantity' => 1],
            ]);
            $this->fail('A RuntimeException was expected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Shopify token exchange failed (HTTP 401).', $exception->getMessage());
        }
    }

    public function test_custom_items_with_a_client_price_are_rejected(): void
    {
        $this->postJson(route('shop.checkout'), [
            'items' => [
                ['type' => 'custom', 'title' => 'Tableau', 'price' => 0.01, 'quantity' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.type', 'items.0.variantId']);

        Http::assertNothingSent();
    }

    public function test_cart_mixing_variant_and_custom_items_is_rejected(): void
    {
        $this->postJson(route('shop.checkout'), [
            'items' => [
                ['type' => 'variant', 'variantId' => 'gid://shopify/ProductVariant/123', 'quantity' => 1],
                ['type' => 'custom', 'title' => 'Tableau', 'price' => 0.01, 'quantity' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.type']);

        Http::assertNothingSent();
    }

    public function test_variant_id_is_required(): void
    {
        $this->postJson(route('shop.checkout'), [
            'items' => [['type' => 'variant', 'quantity' => 1]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.variantId']);
    }

    public function test_quantity_must_be_at_least_one(): void
    {
        $this->postJson(route('shop.checkout'), [
            'items' => [['type' => 'variant', 'variantId' => 'gid://shopify/ProductVariant/123', 'quantity' => 0]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_empty_cart_is_rejected(): void
    {
        $this->postJson(route('shop.checkout'), ['items' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        Http::assertNothingSent();
    }
}
