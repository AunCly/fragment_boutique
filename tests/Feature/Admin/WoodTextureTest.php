<?php

namespace Tests\Feature\Admin;

use App\Models\Palette;
use App\Models\User;
use App\Models\WoodTexture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WoodTextureTest extends TestCase
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

    public function test_create_form_lists_palettes(): void
    {
        Palette::factory()->create(['name' => 'Bois naturels']);

        $this->actingAs($this->admin)
            ->get(route('admin.wood-textures.create'))
            ->assertOk()
            ->assertSee('Bois naturels');
    }

    public function test_admin_can_create_essence_in_palettes(): void
    {
        [$palette, $otherPalette, $untouchedPalette] = Palette::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.wood-textures.store'), [
                'name' => 'Frêne Rose',
                'hex' => '#d8a0a0',
                'texture' => UploadedFile::fake()->image('frene.jpg'),
                'grid_code' => 'AE',
                'sort_order' => 31,
                'is_active' => '1',
                'palette_ids' => [$palette->id, $otherPalette->id],
            ])
            ->assertRedirect(route('admin.wood-textures.index'));

        $texture = WoodTexture::sole();
        $this->assertEqualsCanonicalizing([$palette->id, $otherPalette->id], $texture->palettes->pluck('id')->all());
        $this->assertCount(0, $untouchedPalette->woodTextures);
    }

    public function test_admin_can_create_essence_without_palette(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.wood-textures.store'), [
                'name' => 'Frêne Rose',
                'hex' => '#d8a0a0',
                'texture' => UploadedFile::fake()->image('frene.jpg'),
                'grid_code' => '',
                'sort_order' => 31,
            ])
            ->assertRedirect(route('admin.wood-textures.index'));

        $this->assertCount(0, WoodTexture::sole()->palettes);
    }

    public function test_admin_can_change_essence_palettes(): void
    {
        [$removedPalette, $addedPalette] = Palette::factory()->count(2)->create();
        $texture = WoodTexture::factory()->create();
        $texture->palettes()->attach($removedPalette);

        $this->actingAs($this->admin)
            ->put(route('admin.wood-textures.update', $texture), [
                'name' => $texture->name,
                'hex' => $texture->hex,
                'grid_code' => $texture->grid_code,
                'sort_order' => $texture->sort_order,
                'is_active' => '1',
                'palette_ids' => [$addedPalette->id],
            ])
            ->assertRedirect(route('admin.wood-textures.index'));

        $this->assertSame([$addedPalette->id], $texture->fresh()->palettes->pluck('id')->all());
    }

    public function test_unchecking_all_palettes_detaches_essence(): void
    {
        $palette = Palette::factory()->create();
        $texture = WoodTexture::factory()->create();
        $texture->palettes()->attach($palette);

        $this->actingAs($this->admin)->put(route('admin.wood-textures.update', $texture), [
            'name' => $texture->name,
            'hex' => $texture->hex,
            'grid_code' => $texture->grid_code,
            'sort_order' => $texture->sort_order,
        ]);

        $this->assertCount(0, $texture->fresh()->palettes);
    }

    public function test_essence_rejects_unknown_palette(): void
    {
        $texture = WoodTexture::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.wood-textures.update', $texture), [
                'name' => $texture->name,
                'hex' => $texture->hex,
                'grid_code' => $texture->grid_code,
                'sort_order' => $texture->sort_order,
                'palette_ids' => [9999],
            ])
            ->assertSessionHasErrors('palette_ids.0');
    }

    public function test_deleting_essence_removes_it_from_palettes(): void
    {
        $palette = Palette::factory()->create();
        $texture = WoodTexture::factory()->create();
        $palette->woodTextures()->attach($texture);

        $this->actingAs($this->admin)
            ->delete(route('admin.wood-textures.destroy', $texture))
            ->assertRedirect(route('admin.wood-textures.index'));

        $this->assertModelExists($palette);
        $this->assertDatabaseCount('palette_wood_texture', 0);
    }
}
