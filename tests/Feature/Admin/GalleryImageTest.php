<?php

namespace Tests\Feature\Admin;

use App\Models\GalleryImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryImageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.gallery-images.index'))->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_is_redirected_to_login(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.gallery-images.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_gallery_images(): void
    {
        $image = GalleryImage::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.gallery-images.index'))
            ->assertOk()
            ->assertViewIs('admin.gallery.index')
            ->assertSee($image->url)
            ->assertSee(route('admin.gallery-images.index'));
    }

    public function test_admin_can_upload_multiple_images(): void
    {
        GalleryImage::factory()->create(['sort_order' => 5]);

        $response = $this->actingAs($this->admin)->post(route('admin.gallery-images.store'), [
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.png'),
            ],
            'alt' => 'Un tableau dans un salon',
        ]);

        $response->assertRedirect(route('admin.gallery-images.index'));
        $response->assertSessionHas('success', '2 images ajoutées.');

        $uploaded = GalleryImage::where('alt', 'Un tableau dans un salon')->orderBy('sort_order')->get();

        $this->assertCount(2, $uploaded);
        $this->assertSame([6, 7], $uploaded->pluck('sort_order')->all());
        $uploaded->each(fn (GalleryImage $image) => Storage::disk('public')->assertExists($image->path));
    }

    public function test_upload_requires_at_least_one_image(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.gallery-images.store'), ['images' => []])
            ->assertSessionHasErrors('images');

        $this->assertDatabaseCount('gallery_images', 0);
    }

    public function test_upload_rejects_non_image_files(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.gallery-images.store'), [
                'images' => [UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
            ])
            ->assertSessionHasErrors('images.0');

        $this->assertDatabaseCount('gallery_images', 0);
    }

    public function test_upload_rejects_images_larger_than_five_megabytes(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.gallery-images.store'), [
                'images' => [UploadedFile::fake()->image('huge.jpg')->size(6000)],
            ])
            ->assertSessionHasErrors('images.0');

        $this->assertDatabaseCount('gallery_images', 0);
    }

    public function test_admin_can_update_image_alt_and_position(): void
    {
        $image = GalleryImage::factory()->create(['alt' => 'Ancien', 'sort_order' => 1]);

        $this->actingAs($this->admin)
            ->put(route('admin.gallery-images.update', $image), ['alt' => 'Nouveau', 'sort_order' => 3])
            ->assertRedirect(route('admin.gallery-images.index'));

        $image->refresh();
        $this->assertSame('Nouveau', $image->alt);
        $this->assertSame(3, $image->sort_order);
    }

    public function test_update_requires_valid_position(): void
    {
        $image = GalleryImage::factory()->create(['sort_order' => 1]);

        $this->actingAs($this->admin)
            ->put(route('admin.gallery-images.update', $image), ['sort_order' => -1])
            ->assertSessionHasErrors('sort_order');

        $this->assertSame(1, $image->refresh()->sort_order);
    }

    public function test_admin_can_delete_image_and_its_file(): void
    {
        $path = UploadedFile::fake()->image('photo.jpg')->store('gallery', 'public');
        $image = GalleryImage::factory()->create(['path' => $path]);

        $this->actingAs($this->admin)
            ->delete(route('admin.gallery-images.destroy', $image))
            ->assertRedirect(route('admin.gallery-images.index'))
            ->assertSessionHas('success', 'Image supprimée.');

        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($path);
    }
}
