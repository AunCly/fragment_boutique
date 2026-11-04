<?php

namespace Tests\Feature\Admin;

use App\Models\WoodTexture;
use Database\Seeders\WoodTextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WoodTextureSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_essence_gets_its_photo_on_the_public_disk(): void
    {
        Storage::fake('public');

        $this->seed(WoodTextureSeeder::class);

        $this->assertSame(30, WoodTexture::count());
        foreach (WoodTexture::all() as $texture) {
            Storage::disk('public')->assertExists($texture->texture_path);
        }
    }

    public function test_an_uploaded_replacement_photo_is_kept(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('wood_textures/Chene.jpg', 'uploaded');

        $this->seed(WoodTextureSeeder::class);
        $this->seed(WoodTextureSeeder::class);

        $this->assertSame('uploaded', Storage::disk('public')->get('wood_textures/Chene.jpg'));
        $this->assertSame(30, WoodTexture::count());
    }
}
