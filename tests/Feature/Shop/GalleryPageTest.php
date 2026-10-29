<?php

namespace Tests\Feature\Shop;

use App\Models\GalleryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_gallery_displays_images_in_order(): void
    {
        $second = GalleryImage::factory()->create(['sort_order' => 2, 'alt' => 'Deuxième image']);
        $first = GalleryImage::factory()->create(['sort_order' => 1, 'alt' => 'Première image']);

        $this->get(route('shop.gallery'))
            ->assertOk()
            ->assertSeeInOrder([$first->url, $second->url])
            ->assertSee('Première image')
            ->assertSee('Deuxième image');
    }

    public function test_gallery_uses_default_alt_when_missing(): void
    {
        GalleryImage::factory()->create(['alt' => null]);

        $this->get(route('shop.gallery'))
            ->assertOk()
            ->assertSee(GalleryImage::DEFAULT_ALT);
    }

    public function test_gallery_renders_without_images(): void
    {
        $this->get(route('shop.gallery'))
            ->assertOk()
            ->assertViewHas('images', fn ($images) => $images->isEmpty());
    }
}
