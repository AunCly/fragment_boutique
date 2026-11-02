<?php

namespace Tests\Feature\FragmentTool\Concerns;

use App\Models\Format;
use App\Models\Palette;
use App\Models\WoodTexture;
use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\SourceImage;
use App\Services\FragmentTool\ToolPalette;
use Illuminate\Http\UploadedFile;

trait BuildsToolFixtures
{
    /**
     * The original FragmentTool palette (spec §3).
     *
     * @var array<string, array{0: string, 1: string, 2: string}> slug => [code, name, hex]
     */
    protected array $essenceFixtures = [
        'sycomore' => ['1', 'Sycomore', '#f2cc96'],
        'hetre' => ['2', 'Hêtre', '#d6b47c'],
        'orme' => ['3', 'Orme', '#ad7944'],
        'merisier' => ['4', 'Merisier', '#c26d2d'],
        'poirier_alisier' => ['5', 'Poirier', '#d19f84'],
        'noyer' => ['6', 'Noyer', '#94785f'],
        'chene_fume' => ['7', 'Chêne fumé', '#524437'],
        'tulipier_noir' => ['8', 'Tulipier Noir', '#17100e'],
    ];

    protected function createToolPalette(): Palette
    {
        $palette = Palette::factory()->create(['name' => 'Bois naturels']);

        $sortOrder = 1;
        foreach ($this->essenceFixtures as $slug => [$code, $name, $hex]) {
            $palette->woodTextures()->attach(WoodTexture::factory()->create([
                'slug' => $slug,
                'name' => $name,
                'hex' => $hex,
                'grid_code' => $code,
                'texture_path' => null,
                'sort_order' => $sortOrder++,
            ]));
        }

        return $palette->load('woodTextures');
    }

    protected function toolPalette(?Palette $palette = null): ToolPalette
    {
        return ToolPalette::fromPalette($palette ?? $this->createToolPalette());
    }

    protected function essenceId(Palette|ToolPalette $palette, string $slug): int
    {
        $textures = $palette instanceof Palette ? $palette->woodTextures : null;

        if ($textures) {
            return $textures->firstWhere('slug', $slug)->id;
        }

        foreach ($palette->all() as $essence) {
            if ($essence->slug === $slug) {
                return $essence->id;
            }
        }

        $this->fail("Unknown essence {$slug}.");
    }

    protected function createToolFormat(int $cols = 16, int $rows = 16): Format
    {
        return Format::factory()->forTool()->create(['cols' => $cols, 'rows' => $rows]);
    }

    /**
     * Image whose cells (cell × cell pixels) take the colours of the hex grid.
     *
     * @param  list<list<string>>  $hexGrid
     */
    protected function pixelArtPng(array $hexGrid, int $cell = 8, bool $transparentFirstColour = false): string
    {
        $rows = count($hexGrid);
        $cols = count($hexGrid[0]);
        $image = SourceImage::blank($cols * $cell, $rows * $cell);
        imagealphablending($image, false);

        foreach ($hexGrid as $r => $line) {
            foreach ($line as $c => $hex) {
                [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');
                $alpha = $transparentFirstColour && $hex === $hexGrid[0][0] ? 127 : 0;
                $color = imagecolorallocatealpha($image, $red, $green, $blue, $alpha);
                imagefilledrectangle($image, $c * $cell, $r * $cell, ($c + 1) * $cell - 1, ($r + 1) * $cell - 1, $color);
            }
        }

        return SourceImage::toPng($image);
    }

    /**
     * Square subject of $subjectHex centred on a $backgroundHex background.
     *
     * @return list<list<string>>
     */
    protected function subjectHexGrid(int $cols, int $rows, string $backgroundHex, string $subjectHex, int $margin = 4): array
    {
        $grid = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $inside = $r >= $margin && $c >= $margin && $r < $rows - $margin && $c < $cols - $margin;
                $grid[$r][$c] = $inside ? $subjectHex : $backgroundHex;
            }
        }

        return $grid;
    }

    protected function uploadedPng(string $bytes, string $name = 'chat.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    /**
     * @param  list<list<int>>|null  $cells
     */
    protected function gridOf(int $cols, int $rows, int $id, ?array $cells = null): Grid
    {
        return $cells ? new Grid($cells) : Grid::filled($cols, $rows, $id);
    }
}
