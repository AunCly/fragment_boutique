<?php

namespace Tests\Feature\FragmentTool;

use App\Models\Palette;
use App\Models\WoodTexture;
use App\Services\FragmentTool\Color;
use App\Services\FragmentTool\CropRect;
use App\Services\FragmentTool\Essence;
use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\ToolPalette;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\FragmentTool\Concerns\BuildsToolFixtures;
use Tests\TestCase;

class ColorAndGridTest extends TestCase
{
    use BuildsToolFixtures;
    use RefreshDatabase;

    public function test_ciede2000_matches_reference_values(): void
    {
        // Sharma, Wu & Dalal (2005) test data, pairs 1 and 17.
        $this->assertEqualsWithDelta(2.0425, Color::ciede2000([50.0, 2.6772, -79.7751], [50.0, 0.0, -82.7485]), 0.0001);
        $this->assertEqualsWithDelta(27.1492, Color::ciede2000([50.0, 2.5, 0.0], [73.0, 25.0, -18.0]), 0.0001);
        $this->assertSame(0.0, Color::ciede2000([40.0, 10.0, 20.0], [40.0, 10.0, 20.0]));
    }

    public function test_rgb_to_lab_and_luminance(): void
    {
        $this->assertEqualsWithDelta(100.0, Color::rgbToLab([255, 255, 255])[0], 0.01);
        $this->assertEqualsWithDelta(0.0, Color::rgbToLab([0, 0, 0])[0], 0.01);
        $this->assertEqualsWithDelta(1.0, Color::luminance([255, 255, 255]), 0.0001);
        $this->assertSame([214, 180, 124], Color::hexToRgb('#d6b47c'));
    }

    public function test_palette_resolves_defaults_from_slugs(): void
    {
        $palette = $this->createToolPalette();
        $toolPalette = ToolPalette::fromPalette($palette);

        $this->assertSame($this->essenceId($palette, 'hetre'), $toolPalette->defaultBackgroundId());
        $this->assertSame($this->essenceId($palette, 'sycomore'), $toolPalette->haloId());
        $this->assertSame($this->essenceId($palette, 'poirier_alisier'), $toolPalette->defaultMotifId());
        $this->assertSame($this->essenceId($palette, 'tulipier_noir'), $toolPalette->darkestId());
        $this->assertSame('Bois naturels', $toolPalette->name);
    }

    public function test_palette_falls_back_to_lightest_essence_without_default_slugs(): void
    {
        $palette = new ToolPalette([
            new Essence(1, 'noir', 'N', 'Noir', '#111111'),
            new Essence(2, 'clair', 'C', 'Clair', '#eeeeee'),
            new Essence(3, 'moyen', 'M', 'Moyen', '#888888'),
        ]);

        $this->assertSame(2, $palette->defaultBackgroundId());
        $this->assertSame(2, $palette->haloId());
        $this->assertSame(3, $palette->defaultMotifId());
    }

    public function test_palette_needs_two_essences(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ToolPalette([new Essence(1, 'seul', 'S', 'Seul', '#111111')]);
    }

    public function test_essence_code_falls_back_to_id_without_grid_code(): void
    {
        $texture = WoodTexture::factory()->create(['grid_code' => null]);

        $this->assertSame((string) $texture->id, Essence::fromWoodTexture($texture)->code);
    }

    public function test_grid_parse_accepts_valid_grid(): void
    {
        $palette = $this->toolPalette();
        $id = $palette->ids()[0];
        $cells = array_fill(0, 2, array_fill(0, 3, $id));

        $grid = Grid::parse(json_encode($cells), 3, 2, $palette);

        $this->assertSame($cells, $grid->toArray());
        $this->assertSame([$id => 6], $grid->counts());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidGrids(): array
    {
        return [
            'not json' => ['nope'],
            'wrong size' => ['[[ID,ID],[ID,ID]]'],
            'ragged' => ['[[ID,ID,ID],[ID,ID]]'],
            'unknown essence' => ['[[ID,ID,ID],[ID,ID,999999]]'],
            'not integers' => ['[["a",ID,ID],[ID,ID,ID]]'],
            'empty' => ['[]'],
        ];
    }

    #[DataProvider('invalidGrids')]
    public function test_grid_parse_rejects_invalid_grids(string $json): void
    {
        $palette = $this->toolPalette();

        $this->expectException(InvalidArgumentException::class);

        Grid::parse(str_replace('ID', (string) $palette->ids()[0], $json), 3, 2, $palette);
    }

    public function test_crop_must_match_format_ratio_and_size(): void
    {
        (new CropRect(0, 0, 200, 100))->validateFor(300, 300, 32, 16);
        CropRect::centred(300, 300, 32, 16)->validateFor(300, 300, 32, 16);

        $this->assertSame([0, 75, 300, 150], array_values((array) CropRect::centred(300, 300, 32, 16)));

        foreach ([new CropRect(0, 0, 100, 100), new CropRect(250, 0, 100, 50), new CropRect(0, 0, 20, 10)] as $crop) {
            try {
                $crop->validateFor(300, 300, 32, 16);
                $this->fail('Invalid crop accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_palette_model_lists_its_essences_for_the_tool(): void
    {
        $palette = $this->createToolPalette();

        $this->assertCount(8, Palette::with('woodTextures')->find($palette->id)->woodTextures);
    }
}
