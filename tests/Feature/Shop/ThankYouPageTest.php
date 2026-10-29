<?php

namespace Tests\Feature\Shop;

use Tests\TestCase;

class ThankYouPageTest extends TestCase
{
    public function test_thank_you_page_is_displayed(): void
    {
        $this->withoutVite();

        $response = $this->get(route('shop.thank-you'));

        $response->assertOk();
        $response->assertViewIs('shop.thank-you');
        $response->assertSee('Merci pour votre');
    }

    public function test_thank_you_page_clears_the_cart(): void
    {
        $this->withoutVite();

        $response = $this->get(route('shop.thank-you'));

        $response->assertSee('x-init="items = []"', false);
    }
}
