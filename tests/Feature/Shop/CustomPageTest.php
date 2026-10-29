<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_coming_soon_page_is_displayed_when_builder_is_disabled(): void
    {
        $this->withoutVite();
        config(['app.builder_enabled' => false]);

        $response = $this->get(route('shop.custom'));

        $response->assertOk();
        $response->assertViewIs('shop.custom-coming-soon');
        $response->assertSee('Le sur-mesure arrive bientôt.');
        $response->assertDontSee('builder-root');
    }

    public function test_builder_is_displayed_when_enabled(): void
    {
        $this->withoutVite();
        config(['app.builder_enabled' => true]);

        $response = $this->get(route('shop.custom'));

        $response->assertOk();
        $response->assertViewIs('shop.custom');
        $response->assertSee('builder-root');
        $response->assertViewHas(['woodTextures', 'formats']);
    }
}
