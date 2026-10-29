<?php

namespace Tests\Feature\Shop;

use App\Models\GalleryImage;
use App\Services\ShopifyStorefrontService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');

        $this->mock(ShopifyStorefrontService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('fetchProducts')->andReturn([]);
        });
    }

    public function test_home_page_displays_first_three_gallery_images_in_order(): void
    {
        $fourth = GalleryImage::factory()->create(['sort_order' => 4]);
        $second = GalleryImage::factory()->create(['sort_order' => 2]);
        $first = GalleryImage::factory()->create(['sort_order' => 1]);
        $third = GalleryImage::factory()->create(['sort_order' => 3]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSeeInOrder([$first->url, $second->url, $third->url])
            ->assertDontSee($fourth->url)
            ->assertSee(route('shop.gallery'));
    }

    public function test_home_page_renders_without_gallery_images(): void
    {
        $this->get(route('shop.index'))
            ->assertOk()
            ->assertViewHas('galleryImages', fn ($images) => $images->isEmpty());
    }
}
