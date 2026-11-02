<?php

namespace Tests\Feature\Admin;

use App\Models\Palette;
use App\Models\User;
use App\Models\WoodTexture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaletteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.palettes.index'))->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_is_redirected_to_login(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.palettes.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_palettes_with_their_essences(): void
    {
        $texture = WoodTexture::factory()->create(['name' => 'Merisier', 'grid_code' => 'P']);
        $palette = Palette::factory()->create(['name' => 'Bois naturels']);
        $palette->woodTextures()->attach($texture);

        $this->actingAs($this->admin)
            ->get(route('admin.palettes.index'))
            ->assertOk()
            ->assertViewIs('admin.tool.palettes.index')
            ->assertSee('Bois naturels')
            ->assertSee('P · Merisier');
    }

    public function test_create_form_lists_all_essences(): void
    {
        $active = WoodTexture::factory()->create(['name' => 'Noyer']);
        $inactive = WoodTexture::factory()->create(['name' => 'Padouk', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.palettes.create'))
            ->assertOk()
            ->assertSee($active->name)
            ->assertSee($inactive->name);
    }

    public function test_admin_can_create_palette_with_essences(): void
    {
        $textures = WoodTexture::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.palettes.store'), [
                'name' => 'Bois naturels',
                'description' => 'Tons bruns',
                'sort_order' => 2,
                'is_active' => '1',
                'wood_texture_ids' => $textures->pluck('id')->all(),
            ])
            ->assertRedirect(route('admin.palettes.index'))
            ->assertSessionHas('success', 'Palette ajoutée.');

        $palette = Palette::sole();
        $this->assertSame('Bois naturels', $palette->name);
        $this->assertTrue($palette->is_active);
        $this->assertEqualsCanonicalizing($textures->pluck('id')->all(), $palette->woodTextures->pluck('id')->all());
    }

    public function test_palette_is_inactive_when_checkbox_is_unchecked(): void
    {
        $textures = WoodTexture::factory()->count(2)->create();

        $this->actingAs($this->admin)->post(route('admin.palettes.store'), [
            'name' => 'Brouillon',
            'sort_order' => 0,
            'wood_texture_ids' => $textures->pluck('id')->all(),
        ]);

        $this->assertFalse(Palette::sole()->is_active);
    }

    public function test_palette_requires_at_least_two_essences(): void
    {
        $texture = WoodTexture::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.palettes.store'), [
                'name' => 'Trop courte',
                'sort_order' => 0,
                'wood_texture_ids' => [$texture->id],
            ])
            ->assertSessionHasErrors(['wood_texture_ids' => 'Sélectionnez au moins 2 essences.']);

        $this->assertDatabaseCount('palettes', 0);
    }

    public function test_palette_requires_essences(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.palettes.store'), ['name' => 'Vide', 'sort_order' => 0])
            ->assertSessionHasErrors('wood_texture_ids');

        $this->assertDatabaseCount('palettes', 0);
    }

    public function test_palette_rejects_unknown_essences(): void
    {
        $texture = WoodTexture::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.palettes.store'), [
                'name' => 'Inconnue',
                'sort_order' => 0,
                'wood_texture_ids' => [$texture->id, 9999],
            ])
            ->assertSessionHasErrors('wood_texture_ids.1');

        $this->assertDatabaseCount('palettes', 0);
    }

    public function test_palette_rejects_duplicate_essences(): void
    {
        $texture = WoodTexture::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.palettes.store'), [
                'name' => 'Doublon',
                'sort_order' => 0,
                'wood_texture_ids' => [$texture->id, $texture->id],
            ])
            ->assertSessionHasErrors('wood_texture_ids.0');

        $this->assertDatabaseCount('palettes', 0);
    }

    public function test_palette_requires_name(): void
    {
        $textures = WoodTexture::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.palettes.store'), [
                'sort_order' => 0,
                'wood_texture_ids' => $textures->pluck('id')->all(),
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_edit_form_checks_current_essences(): void
    {
        $palette = Palette::factory()->create();
        $selected = WoodTexture::factory()->create();
        $unselected = WoodTexture::factory()->create();
        $palette->woodTextures()->attach($selected);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.palettes.edit', $palette))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/value="'.$selected->id.'"\s+class="rounded border-border"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="'.$unselected->id.'"\s+class="rounded border-border"\s+checked/', $html);
    }

    public function test_admin_can_update_palette_and_replace_essences(): void
    {
        $palette = Palette::factory()->create(['name' => 'Ancienne']);
        [$kept, $removed, $added, $alsoAdded] = WoodTexture::factory()->count(4)->create();
        $palette->woodTextures()->attach([$kept->id, $removed->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.palettes.update', $palette), [
                'name' => 'Nouvelle',
                'sort_order' => 1,
                'is_active' => '1',
                'wood_texture_ids' => [$kept->id, $added->id, $alsoAdded->id],
            ])
            ->assertRedirect(route('admin.palettes.index'))
            ->assertSessionHas('success', 'Palette mise à jour.');

        $palette->refresh();
        $this->assertSame('Nouvelle', $palette->name);
        $this->assertEqualsCanonicalizing(
            [$kept->id, $added->id, $alsoAdded->id],
            $palette->woodTextures->pluck('id')->all(),
        );
    }

    public function test_admin_can_delete_palette_without_deleting_essences(): void
    {
        $palette = Palette::factory()->create();
        $textures = WoodTexture::factory()->count(2)->create();
        $palette->woodTextures()->attach($textures);

        $this->actingAs($this->admin)
            ->delete(route('admin.palettes.destroy', $palette))
            ->assertRedirect(route('admin.palettes.index'))
            ->assertSessionHas('success', 'Palette supprimée.');

        $this->assertModelMissing($palette);
        $this->assertDatabaseCount('palette_wood_texture', 0);
        $this->assertDatabaseCount('wood_textures', 2);
    }

    public function test_palette_essences_are_ordered_like_the_essence_list(): void
    {
        $palette = Palette::factory()->create();
        $last = WoodTexture::factory()->create(['sort_order' => 9]);
        $first = WoodTexture::factory()->create(['sort_order' => 1]);
        $palette->woodTextures()->attach([$last->id, $first->id]);

        $this->assertSame([$first->id, $last->id], $palette->woodTextures->pluck('id')->all());
    }
}
