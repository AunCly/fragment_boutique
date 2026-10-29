<?php

namespace Tests\Feature\Shop;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::flush();
    }

    public function test_product_page_displays_metafield_sections(): void
    {
        $this->fakeProduct([
            'kitContents' => ['type' => 'list.single_line_text_field', 'value' => json_encode(['Cadre en chêne massif', 'Pince en bois'])],
            'woods' => ['type' => 'multi_line_text_field', 'value' => "Chêne français\nNoyer"],
            'assemblyTime' => ['type' => 'single_line_text_field', 'value' => 'Une à trois soirées'],
        ]);

        $response = $this->get(route('shop.product', ['id' => 123]));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Ce que vous recevez',
            '— Cadre en chêne massif',
            '— Pince en bois',
            'Bois utilisés',
            'Chêne français',
            'Noyer',
            'Temps d&#039;assemblage estimé :',
            'Une à trois soirées',
        ], false);
    }

    public function test_product_page_splits_multi_line_kit_contents(): void
    {
        $this->fakeProduct([
            'kitContents' => ['type' => 'multi_line_text_field', 'value' => "Cadre en chêne massif\n\nPince en bois\n"],
        ]);

        $response = $this->get(route('shop.product', ['id' => 123]));

        $response->assertOk();
        $response->assertSeeInOrder(['— Cadre en chêne massif', '— Pince en bois']);
        $response->assertDontSee('— </li>', false);
    }

    public function test_product_page_hides_sections_without_metafields(): void
    {
        $this->fakeProduct([
            'kitContents' => null,
            'woods' => ['type' => 'single_line_text_field', 'value' => '   '],
            'assemblyTime' => null,
        ]);

        $response = $this->get(route('shop.product', ['id' => 123]));

        $response->assertOk();
        $response->assertDontSee('Ce que vous recevez');
        $response->assertDontSee('Bois utilisés');
        $response->assertDontSee('Temps d&#039;assemblage estimé', false);
    }

    public function test_product_page_returns_404_when_product_is_missing(): void
    {
        Http::fake(['*' => Http::response(['data' => ['product' => null]])]);

        $this->get(route('shop.product', ['id' => 123]))->assertNotFound();
    }

    /**
     * @param  array<string, array{type: string, value: string}|null>  $metafields
     */
    private function fakeProduct(array $metafields): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'product' => [
                        'id' => 'gid://shopify/Product/123',
                        'title' => 'Fleur de cerisier',
                        'productType' => 'Kit',
                        'tags' => [],
                        'descriptionHtml' => '',
                        'images' => ['edges' => []],
                        'options' => [['name' => 'Title', 'values' => ['Default Title']]],
                        'variants' => ['edges' => [[
                            'node' => [
                                'id' => 'gid://shopify/ProductVariant/1',
                                'availableForSale' => true,
                                'price' => ['amount' => '89.0', 'currencyCode' => 'EUR'],
                                'selectedOptions' => [['name' => 'Title', 'value' => 'Default Title']],
                                'image' => null,
                            ],
                        ]]],
                        ...$metafields,
                    ],
                ],
            ]),
        ]);
    }
}
