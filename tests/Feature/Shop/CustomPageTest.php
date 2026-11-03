<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_coming_soon_page_is_displayed(): void
    {
        $this->withoutVite();

        $response = $this->get(route('shop.custom'));

        $response->assertOk();
        $response->assertViewIs('shop.custom-coming-soon');
        $response->assertSee('Le sur-mesure arrive bientôt.');
    }
}
