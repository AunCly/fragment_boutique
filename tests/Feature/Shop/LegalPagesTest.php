<?php

namespace Tests\Feature\Shop;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_terms_page_is_displayed(): void
    {
        $this->get(route('shop.terms'))
            ->assertOk()
            ->assertViewIs('shop.legal.terms')
            ->assertSee('Conditions générales de vente')
            ->assertSee('Droit de rétractation')
            ->assertSee(route('shop.shipping-returns'));
    }

    public function test_legal_notice_page_is_displayed(): void
    {
        $this->get(route('shop.legal-notice'))
            ->assertOk()
            ->assertViewIs('shop.legal.legal-notice')
            ->assertSee('Éditeur du site')
            ->assertSee('Hébergement');
    }

    public function test_shipping_returns_page_is_displayed(): void
    {
        $this->get(route('shop.shipping-returns'))
            ->assertOk()
            ->assertViewIs('shop.legal.shipping-returns')
            ->assertSee('Zones et tarifs de livraison')
            ->assertSee('Retours')
            ->assertSee(route('shop.terms'));
    }

    public function test_footer_links_to_legal_pages(): void
    {
        $this->get(route('shop.shipping-returns'))
            ->assertOk()
            ->assertSee('href="'.route('shop.terms').'"', false)
            ->assertSee('href="'.route('shop.legal-notice').'"', false)
            ->assertSee('href="'.route('shop.shipping-returns').'"', false);
    }
}
