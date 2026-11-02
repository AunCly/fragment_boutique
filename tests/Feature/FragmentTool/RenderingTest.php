<?php

namespace Tests\Feature\FragmentTool;

use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\Rendering\FlatRenderer;
use App\Services\FragmentTool\Rendering\PlanRenderer;
use App\Services\FragmentTool\Rendering\WoodRenderer;
use App\Services\FragmentTool\ToolPalette;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FragmentTool\Concerns\BuildsToolFixtures;
use Tests\TestCase;

class RenderingTest extends TestCase
{
    use BuildsToolFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        WoodRenderer::flushCache();
    }

    private function twoEssenceGrid(ToolPalette $palette, int $cols, int $rows): Grid
    {
        [$first, $second] = $palette->ids();
        $cells = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $cells[$r][$c] = $c < intdiv($cols, 4) ? $second : $first;
            }
        }

        return new Grid($cells);
    }

    /**
     * Photo with vertical (or horizontal) stripes, like wood grain.
     */
    private function grainPhoto(bool $vertical): string
    {
        $image = imagecreatetruecolor(200, 200);
        for ($i = 0; $i < 200; $i++) {
            $shade = $i % 8 < 4 ? 150 : 90;
            $color = imagecolorallocate($image, $shade, (int) ($shade * 0.7), (int) ($shade * 0.4));
            $vertical ? imageline($image, $i, 0, $i, 199, $color) : imageline($image, 0, $i, 199, $i, $color);
        }
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    public function test_flat_render_has_32_px_cells_and_only_palette_colours(): void
    {
        $palette = $this->toolPalette();
        $grid = $this->twoEssenceGrid($palette, 16, 16);

        $image = imagecreatefromstring((new FlatRenderer)->render($grid, $palette));

        $this->assertSame([512, 512], [imagesx($image), imagesy($image)]);
        $allowed = array_map(fn ($essence) => ($essence->rgb[0] << 16) | ($essence->rgb[1] << 8) | $essence->rgb[2], $palette->all());
        foreach ([[0, 0], [100, 300], [511, 511], [40, 40]] as [$x, $y]) {
            $this->assertContains(imagecolorat($image, $x, $y) & 0xFFFFFF, $allowed);
        }
    }

    public function test_flat_render_of_a_rectangular_format(): void
    {
        $palette = $this->toolPalette();
        $image = imagecreatefromstring((new FlatRenderer)->render($this->twoEssenceGrid($palette, 48, 24), $palette));

        $this->assertSame([48 * 32, 24 * 32], [imagesx($image), imagesy($image)]);
    }

    public function test_plan_has_a_legend_below_the_grid(): void
    {
        $palette = $this->toolPalette();
        $grid = $this->twoEssenceGrid($palette, 16, 16);

        $image = imagecreatefromstring((new PlanRenderer)->render($grid, $palette, 'chat', 'gemini-nano-banana-2.1'));

        $this->assertGreaterThanOrEqual(16 * 40, imagesx($image));
        // Header + grid + margin + legend (2 essences + total).
        $this->assertGreaterThan(90 + 16 * 40 + 40 + 3 * 34, imagesy($image));
        $this->assertSame(0xFFFFFF, imagecolorat($image, 2, 2) & 0xFFFFFF, 'White background.');
    }

    public function test_plan_cells_shrink_for_large_formats(): void
    {
        $palette = $this->toolPalette();
        $image = imagecreatefromstring((new PlanRenderer)->render($this->twoEssenceGrid($palette, 96, 48), $palette, 'paysage'));

        $this->assertLessThanOrEqual(2400 + 80, imagesx($image));
    }

    public function test_plan_markers_every_4_cells_in_16_and_8_beyond(): void
    {
        $this->assertSame(4, PlanRenderer::markerEvery(Grid::filled(16, 16, 1)));
        $this->assertSame(8, PlanRenderer::markerEvery(Grid::filled(32, 32, 1)));
        $this->assertSame(8, PlanRenderer::markerEvery(Grid::filled(48, 24, 1)));
    }

    public function test_plan_numbers_are_readable_on_dark_woods(): void
    {
        $renderer = new PlanRenderer;

        $this->assertSame('white', $renderer->readableOn([0x17, 0x10, 0x0E]));
        $this->assertSame('white', $renderer->readableOn([0x52, 0x44, 0x37]));
        $this->assertSame('black', $renderer->readableOn([0xF2, 0xCC, 0x96]));
    }

    public function test_wood_render_falls_back_to_flat_without_photos(): void
    {
        $palette = $this->toolPalette();
        $grid = $this->twoEssenceGrid($palette, 32, 32);
        $renderer = new WoodRenderer;

        $image = imagecreatefromstring($renderer->render($grid, $palette));

        $this->assertSame([1536, 1536], [imagesx($image), imagesy($image)]);
        $this->assertSame([], $renderer->essencesWithTexture($palette));
        $first = $palette->get($grid->get(0, 31));
        $this->assertSame(($first->rgb[0] << 16) | ($first->rgb[1] << 8) | $first->rgb[2], imagecolorat($image, 1500, 10) & 0xFFFFFF);
    }

    public function test_wood_render_uses_the_essence_photo_and_is_deterministic(): void
    {
        $palette = $this->createToolPalette();
        $texture = $palette->woodTextures->first();
        Storage::disk('public')->put('wood_textures/sycomore.jpg', $this->grainPhoto(vertical: true));
        $texture->update(['texture_path' => 'wood_textures/sycomore.jpg']);
        $toolPalette = $this->toolPalette($palette->fresh('woodTextures'));
        $grid = Grid::filled(16, 16, $texture->id);
        $renderer = new WoodRenderer;

        $first = $renderer->render($grid, $toolPalette);

        $this->assertSame([$texture->id], $renderer->essencesWithTexture($toolPalette));
        $this->assertSame($first, $renderer->render($grid, $toolPalette));
        $image = imagecreatefromstring($first);
        $this->assertSame([1536, 1536], [imagesx($image), imagesy($image)]);
        $this->assertTrue(
            imagecolorat($image, 10, 10) !== imagecolorat($image, 50, 10) || imagecolorat($image, 10, 10) !== imagecolorat($image, 10, 50),
            'The photo grain is visible, whatever the piece orientation.',
        );
    }

    public function test_wood_pieces_are_turned_by_random_quarter_turns(): void
    {
        $palette = $this->createToolPalette();
        $texture = $palette->woodTextures->first();
        Storage::disk('public')->put('wood_textures/sycomore.jpg', $this->grainPhoto(vertical: true));
        $texture->update(['texture_path' => 'wood_textures/sycomore.jpg']);
        $toolPalette = $this->toolPalette($palette->fresh('woodTextures'));
        $renderer = new WoodRenderer;

        $image = imagecreatefromstring($renderer->render(Grid::filled(16, 16, $texture->id), $toolPalette));
        $cell = intdiv(imagesx($image), 16);
        $orientations = [];
        for ($row = 0; $row < 16; $row++) {
            for ($col = 0; $col < 16; $col++) {
                $piece = imagecrop($image, ['x' => $col * $cell, 'y' => $row * $cell, 'width' => $cell - 2, 'height' => $cell - 2]);
                $orientations[] = $renderer->hasHorizontalGrain($piece);
            }
        }

        $this->assertContains(true, $orientations, 'Some pieces are turned by 90°.');
        $this->assertContains(false, $orientations, 'Some pieces keep a vertical grain.');
    }

    public function test_grain_orientation_is_detected(): void
    {
        $renderer = new WoodRenderer;

        $this->assertFalse($renderer->hasHorizontalGrain(imagecreatefromstring($this->grainPhoto(vertical: true))));
        $this->assertTrue($renderer->hasHorizontalGrain(imagecreatefromstring($this->grainPhoto(vertical: false))));
    }

    public function test_missing_photo_file_is_rendered_flat(): void
    {
        $palette = $this->createToolPalette();
        $palette->woodTextures->first()->update(['texture_path' => 'wood_textures/absente.jpg']);

        $this->assertSame([], (new WoodRenderer)->essencesWithTexture($this->toolPalette($palette->fresh('woodTextures'))));
    }
}
