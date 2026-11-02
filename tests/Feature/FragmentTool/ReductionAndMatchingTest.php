<?php

namespace Tests\Feature\FragmentTool;

use App\Services\FragmentTool\ConversionPipeline;
use App\Services\FragmentTool\CropRect;
use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\Matching\MatcherRegistry;
use App\Services\FragmentTool\PixelBuffer;
use App\Services\FragmentTool\Reduction\GridDetector;
use App\Services\FragmentTool\Reduction\ReducerRegistry;
use App\Services\FragmentTool\SourceImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\FragmentTool\Concerns\BuildsToolFixtures;
use Tests\TestCase;

class ReductionAndMatchingTest extends TestCase
{
    use BuildsToolFixtures;
    use RefreshDatabase;

    /**
     * Checkerboard-like test pattern using the palette colours.
     *
     * @return list<list<string>>
     */
    private function pattern(int $cols, int $rows): array
    {
        $hexes = array_column(array_values($this->essenceFixtures), 2);
        $grid = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $grid[$r][$c] = $hexes[($r * 3 + $c * 5 + intdiv($c, 3)) % count($hexes)];
            }
        }

        return $grid;
    }

    private function pixels(string $png): PixelBuffer
    {
        return PixelBuffer::fromGd(imagecreatefromstring($png));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reductions(): array
    {
        return array_combine(
            ['grid', 'mean', 'median', 'mode', 'nearest', 'contours'],
            [['grid'], ['mean'], ['median'], ['mode'], ['nearest'], ['contours']],
        );
    }

    #[DataProvider('reductions')]
    public function test_each_reduction_finds_the_exact_colours_of_a_known_grid(string $reduction): void
    {
        $hexGrid = $this->pattern(16, 8);
        $colors = (new ReducerRegistry)->get($reduction)->reduce($this->pixels($this->pixelArtPng($hexGrid, 6)), 16, 8);

        $this->assertSame($hexGrid, array_map(fn (array $row) => array_map(fn (array $rgb) => sprintf('#%02x%02x%02x', ...$rgb), $row), $colors));
    }

    public function test_grid_detection_finds_big_pixels_despite_jpeg_noise(): void
    {
        $image = imagecreatefromstring($this->pixelArtPng($this->pattern(12, 12), 7));
        $jpeg = imagecreatefromstring(SourceImage::toJpeg($image, quality: 85));
        $pixels = PixelBuffer::fromGd($jpeg);

        $grid = (new GridDetector)->detect($pixels);

        $this->assertNotNull($grid);
        $this->assertSame(7.0, $grid['x']['period']);
        $this->assertSame(7.0, $grid['y']['period']);

        $sampled = (new GridDetector)->sample($pixels, $grid);
        $this->assertSame([12, 12], [$sampled->width, $sampled->height]);
    }

    public function test_grid_reduction_handles_images_already_finer_than_the_format(): void
    {
        // 32 × 32 big pixels of 5 px, reduced to 16 × 16 by dominant colour.
        $hexGrid = $this->pattern(32, 32);
        $colors = (new ReducerRegistry)->get('grid')->reduce($this->pixels($this->pixelArtPng($hexGrid, 5)), 16, 16);

        $this->assertCount(16, $colors);
        $this->assertCount(16, $colors[0]);
    }

    public function test_grid_detection_gives_up_on_a_plain_image(): void
    {
        $hexGrid = array_fill(0, 10, array_fill(0, 10, '#d6b47c'));

        $this->assertNull((new GridDetector)->detect($this->pixels($this->pixelArtPng($hexGrid, 8))));
    }

    public function test_contours_keep_a_thin_dark_line_that_mode_erases(): void
    {
        // 64 × 64 light image with a 2 px dark vertical line in the middle of the 4th block column.
        $image = SourceImage::blank(64, 64);
        imagealphablending($image, false);
        imagefilledrectangle($image, 0, 0, 63, 63, imagecolorallocate($image, 0xF2, 0xCC, 0x96));
        imagefilledrectangle($image, 27, 0, 28, 63, imagecolorallocate($image, 0x17, 0x10, 0x0E));
        $pixels = PixelBuffer::fromGd($image);

        $mode = (new ReducerRegistry)->get('mode')->reduce($pixels, 8, 8);
        $contours = (new ReducerRegistry)->get('contours')->reduce($pixels, 8, 8);

        $this->assertSame([0xF2, 0xCC, 0x96], $mode[0][3]);
        $this->assertSame([0x17, 0x10, 0x0E], $contours[0][3]);
        $this->assertSame([0xF2, 0xCC, 0x96], $contours[0][0]);
    }

    public function test_blocks_handle_sizes_that_are_not_multiples_of_the_format(): void
    {
        $image = SourceImage::blank(50, 37);
        imagealphablending($image, false);
        imagefilledrectangle($image, 0, 0, 49, 36, imagecolorallocate($image, 10, 20, 30));

        $colors = (new ReducerRegistry)->get('mean')->reduce(PixelBuffer::fromGd($image), 16, 16);

        $this->assertCount(16, $colors);
        $this->assertSame([10, 20, 30], $colors[15][15]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function matchings(): array
    {
        return array_combine(['rgb', 'redmean', 'lab', 'ciede2000', 'tone'], [['rgb'], ['redmean'], ['lab'], ['ciede2000'], ['tone']]);
    }

    #[DataProvider('matchings')]
    public function test_each_matching_maps_palette_colours_to_themselves(string $matching): void
    {
        $palette = $this->toolPalette();
        $colors = [array_values(array_map(fn ($essence) => $essence->rgb, $palette->all()))];
        $mask = [array_fill(0, count($colors[0]), false)];

        $ids = (new MatcherRegistry)->get($matching)->match($colors, $palette, $mask);

        $this->assertSame([$palette->ids()], $ids);
    }

    public function test_tone_matching_moves_blue_sky_into_wood_tones(): void
    {
        $palette = $this->toolPalette();
        $colors = [[[40, 110, 220], [10, 10, 10], [250, 250, 250]]];

        $ids = (new MatcherRegistry)->get('tone')->match($colors, $palette, [[false, false, false]]);

        foreach ($ids[0] as $id) {
            $this->assertTrue($palette->has($id));
        }
        $this->assertSame($palette->darkestId(), $ids[0][1]);
    }

    public function test_conversion_fills_transparent_cells_with_the_background_essence(): void
    {
        $palette = $this->createToolPalette();
        $toolPalette = $this->toolPalette($palette);
        $merisierId = $this->essenceId($palette, 'merisier');
        $noyerId = $this->essenceId($palette, 'noyer');
        $png = $this->pixelArtPng($this->subjectHexGrid(16, 16, '#000000', '#c26d2d', margin: 4), 10, transparentFirstColour: true);
        $source = SourceImage::fromBytes($png, 'chat');

        $grid = app(ConversionPipeline::class)->convert($source, CropRect::centred(160, 160, 16, 16), 16, 16, $toolPalette, $noyerId, 'mode', 'ciede2000');

        $this->assertSame($noyerId, $grid->get(0, 0));
        $this->assertSame($merisierId, $grid->get(8, 8));
        $this->assertSame(8 * 8, $grid->counts()[$merisierId]);
    }

    public function test_preview_returns_every_combination(): void
    {
        $toolPalette = $this->toolPalette();
        $source = SourceImage::fromBytes($this->pixelArtPng($this->pattern(16, 16), 4));

        $grids = app(ConversionPipeline::class)->preview($source, CropRect::centred(64, 64, 16, 16), 16, 16, $toolPalette, $toolPalette->defaultBackgroundId());

        $this->assertCount(30, $grids);
        $this->assertContainsOnlyInstancesOf(Grid::class, $grids);
        $this->assertArrayHasKey('contours-tone', $grids);
    }

    public function test_preview_of_thirty_combinations_is_fast_enough(): void
    {
        $toolPalette = $this->toolPalette();
        $source = SourceImage::fromBytes($this->pixelArtPng($this->pattern(32, 32), 8));
        $start = microtime(true);

        app(ConversionPipeline::class)->preview($source, CropRect::centred(256, 256, 32, 32), 32, 32, $toolPalette, $toolPalette->defaultBackgroundId());

        $this->assertLessThan(10.0, microtime(true) - $start);
    }
}
