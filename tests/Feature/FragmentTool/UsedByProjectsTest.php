<?php

namespace Tests\Feature\FragmentTool;

use App\Models\Format;
use App\Models\FragmentProject;
use App\Models\Palette;
use App\Models\User;
use App\Models\WoodTexture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\FragmentTool\Concerns\BuildsToolFixtures;
use Tests\TestCase;

/**
 * Palettes, essences and formats used by a saved project can only be archived,
 * so the projects never break.
 */
class UsedByProjectsTest extends TestCase
{
    use BuildsToolFixtures;
    use RefreshDatabase;

    private User $admin;

    private Palette $palette;

    private Format $format;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->palette = $this->createToolPalette();
        $this->format = $this->createToolFormat(16, 16);
    }

    private function useInProject(): FragmentProject
    {
        return FragmentProject::factory()->create([
            'palette_id' => $this->palette->id,
            'format_id' => $this->format->id,
            'background_id' => $this->essenceId($this->palette, 'hetre'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paletteForm(array $woodTextureIds): array
    {
        return [
            'name' => $this->palette->name,
            'description' => null,
            'sort_order' => 1,
            'is_active' => '1',
            'wood_texture_ids' => $woodTextureIds,
        ];
    }

    /* ---------------------------------------------------------------- Palettes */

    public function test_a_used_palette_cannot_be_deleted_only_archived(): void
    {
        $this->useInProject();

        $this->actingAs($this->admin)->get(route('admin.palettes.index'))
            ->assertSee('action="'.route('admin.palettes.archive', $this->palette).'"', false)
            ->assertDontSee('action="'.route('admin.palettes.destroy', $this->palette).'"', false);

        $this->actingAs($this->admin)->delete(route('admin.palettes.destroy', $this->palette))
            ->assertRedirect(route('admin.palettes.index'))
            ->assertSessionHas('error');
        $this->assertModelExists($this->palette);

        $this->actingAs($this->admin)->patch(route('admin.palettes.archive', $this->palette))
            ->assertRedirect(route('admin.palettes.index'));
        $this->assertFalse($this->palette->fresh()->is_active);
    }

    public function test_an_unused_palette_can_still_be_deleted(): void
    {
        $this->actingAs($this->admin)->get(route('admin.palettes.index'))
            ->assertSee('action="'.route('admin.palettes.destroy', $this->palette).'"', false);

        $this->actingAs($this->admin)->delete(route('admin.palettes.destroy', $this->palette))->assertSessionHas('success');

        $this->assertModelMissing($this->palette);
    }

    public function test_a_used_palette_keeps_its_essences_but_can_get_new_ones(): void
    {
        $this->useInProject();
        $ids = $this->palette->woodTextures->pluck('id')->all();
        $extra = WoodTexture::factory()->create()->id;

        $this->actingAs($this->admin)->put(route('admin.palettes.update', $this->palette), $this->paletteForm(array_slice($ids, 1)))
            ->assertSessionHasErrors('wood_texture_ids');
        $this->assertCount(8, $this->palette->woodTextures()->get());

        $this->actingAs($this->admin)->put(route('admin.palettes.update', $this->palette), $this->paletteForm([...$ids, $extra]))
            ->assertSessionHasNoErrors();
        $this->assertCount(9, $this->palette->woodTextures()->get());
    }

    public function test_an_unused_palette_can_lose_essences(): void
    {
        $ids = $this->palette->woodTextures->pluck('id')->all();

        $this->actingAs($this->admin)->put(route('admin.palettes.update', $this->palette), $this->paletteForm(array_slice($ids, 1)))
            ->assertSessionHasNoErrors();

        $this->assertCount(7, $this->palette->woodTextures()->get());
    }

    public function test_a_palette_can_be_duplicated_with_its_essences(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.palettes.duplicate', $this->palette));

        $copy = Palette::latest('id')->first();
        $response->assertRedirect(route('admin.palettes.edit', $copy));
        $this->assertSame('Bois naturels (copie)', $copy->name);
        $this->assertSame($this->palette->woodTextures->pluck('id')->all(), $copy->woodTextures->pluck('id')->all());
    }

    /* ---------------------------------------------------------------- Formats */

    public function test_a_used_format_cannot_be_deleted_only_archived(): void
    {
        $this->useInProject();

        $this->actingAs($this->admin)->get(route('admin.formats.index'))
            ->assertSee('action="'.route('admin.formats.archive', $this->format).'"', false)
            ->assertDontSee('action="'.route('admin.formats.destroy', $this->format).'"', false);

        $this->actingAs($this->admin)->delete(route('admin.formats.destroy', $this->format))->assertSessionHas('error');
        $this->assertModelExists($this->format);

        $this->actingAs($this->admin)->patch(route('admin.formats.archive', $this->format))->assertSessionHas('success');
        $format = $this->format->fresh();
        $this->assertFalse($format->is_active);
        $this->assertFalse($format->is_tool_enabled);
    }

    /* ---------------------------------------------------------------- Essences */

    public function test_an_essence_of_a_used_palette_cannot_be_deleted_only_archived(): void
    {
        $this->useInProject();
        $essence = $this->palette->woodTextures->firstWhere('slug', 'noyer');
        $unused = WoodTexture::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.wood-textures.index'))
            ->assertSee('action="'.route('admin.wood-textures.archive', $essence).'"', false)
            ->assertDontSee('action="'.route('admin.wood-textures.destroy', $essence).'"', false)
            ->assertSee('action="'.route('admin.wood-textures.destroy', $unused).'"', false);

        $this->actingAs($this->admin)->delete(route('admin.wood-textures.destroy', $essence))->assertSessionHas('error');
        $this->assertModelExists($essence);

        $this->actingAs($this->admin)->patch(route('admin.wood-textures.archive', $essence))->assertSessionHas('success');
        $this->assertFalse($essence->fresh()->is_active);
    }

    public function test_an_essence_cannot_leave_a_used_palette(): void
    {
        $this->useInProject();
        $essence = $this->palette->woodTextures->firstWhere('slug', 'noyer');
        $other = Palette::factory()->create();

        $form = [
            'name' => $essence->name,
            'hex' => $essence->hex,
            'grid_code' => $essence->grid_code,
            'sort_order' => 1,
            'is_active' => '1',
        ];

        $this->actingAs($this->admin)->put(route('admin.wood-textures.update', $essence), [...$form, 'palette_ids' => [$other->id]])
            ->assertSessionHasErrors('palette_ids');
        $this->assertSame([$this->palette->id], $essence->palettes()->pluck('palettes.id')->all());

        $this->actingAs($this->admin)->put(route('admin.wood-textures.update', $essence), [...$form, 'palette_ids' => [$this->palette->id, $other->id]])
            ->assertSessionHasNoErrors();
        $this->assertCount(2, $essence->palettes()->get());
    }
}
