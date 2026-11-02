<?php

namespace App\Services\FragmentTool;

use App\Services\FragmentTool\Matching\MatcherRegistry;
use App\Services\FragmentTool\Reduction\ReducerRegistry;
use GdImage;

/**
 * Classic conversion: crop → brightness / contrast → transparency → reduction → matching.
 */
class ConversionPipeline
{
    /** Alpha under which a pixel counts as transparent. */
    public const TRANSPARENT_ALPHA = 128;

    public function __construct(
        private readonly ReducerRegistry $reducers,
        private readonly MatcherRegistry $matchers,
    ) {}

    public function convert(
        SourceImage $source,
        CropRect $crop,
        int $cols,
        int $rows,
        ToolPalette $palette,
        int $backgroundId,
        string $reduction,
        string $matching,
        int $brightness = 0,
        int $contrast = 0,
    ): Grid {
        $image = $source->crop($crop, config('fragment_tool.working_long_side'));
        $this->adjust($image, $brightness, $contrast);

        return $this->reduceAndMatch(PixelBuffer::fromGd($image), $cols, $rows, $palette, $backgroundId, $reduction, $matching);
    }

    /**
     * @param  list<string>|null  $reductions
     * @param  list<string>|null  $matchings
     * @return array<string, Grid> "reduction-matching" => grid, for every combination
     */
    public function preview(
        SourceImage $source,
        CropRect $crop,
        int $cols,
        int $rows,
        ToolPalette $palette,
        int $backgroundId,
        int $brightness = 0,
        int $contrast = 0,
        ?array $reductions = null,
        ?array $matchings = null,
    ): array {
        $image = $source->crop($crop, config('fragment_tool.working_long_side'));
        $this->adjust($image, $brightness, $contrast);
        $pixels = PixelBuffer::fromGd($image);
        [$mask, $composited] = $this->transparency($pixels, $cols, $rows, $palette->get($backgroundId)->rgb);

        $grids = [];
        foreach ($reductions ?? array_keys($this->reducers->labels()) as $reduction) {
            $colors = $this->reducers->get($reduction)->reduce($composited, $cols, $rows);
            foreach ($matchings ?? array_keys($this->matchers->labels()) as $matching) {
                $ids = $this->matchers->get($matching)->match($colors, $palette, $mask);
                $grids["{$reduction}-{$matching}"] = $this->applyBackground($ids, $mask, $backgroundId);
            }
        }

        return $grids;
    }

    public function reduceAndMatch(PixelBuffer $pixels, int $cols, int $rows, ToolPalette $palette, int $backgroundId, string $reduction, string $matching): Grid
    {
        [$mask, $composited] = $this->transparency($pixels, $cols, $rows, $palette->get($backgroundId)->rgb);
        $colors = $this->reducers->get($reduction)->reduce($composited, $cols, $rows);
        $ids = $this->matchers->get($matching)->match($colors, $palette, $mask);

        return $this->applyBackground($ids, $mask, $backgroundId);
    }

    /**
     * Cropped area composited on the background essence, as sent to the AI model.
     */
    public function flattenedCrop(SourceImage $source, CropRect $crop, ToolPalette $palette, int $backgroundId): GdImage
    {
        $image = $source->crop($crop, config('fragment_tool.working_long_side'));
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, ...$palette->get($backgroundId)->rgb));
        imagealphablending($flat, true);
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $flat;
    }

    /**
     * Cells mostly transparent get the background essence; the other pixels are composited on it.
     *
     * @param  array{0: int, 1: int, 2: int}  $background
     * @return array{0: list<list<bool>>, 1: PixelBuffer}
     */
    private function transparency(PixelBuffer $pixels, int $cols, int $rows, array $background): array
    {
        $mask = [];

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $indexes = $pixels->cellIndexes($col, $row, $cols, $rows);
                $transparent = 0;
                foreach ($indexes as $i) {
                    if ($pixels->a[$i] < self::TRANSPARENT_ALPHA) {
                        $transparent++;
                    }
                }
                $mask[$row][$col] = $transparent * 2 > count($indexes);
            }
        }

        return [$mask, $pixels->compositeOn($background)];
    }

    /**
     * @param  list<list<int>>  $ids
     * @param  list<list<bool>>  $mask
     */
    private function applyBackground(array $ids, array $mask, int $backgroundId): Grid
    {
        foreach ($mask as $row => $line) {
            foreach ($line as $col => $isBackground) {
                if ($isBackground) {
                    $ids[$row][$col] = $backgroundId;
                }
            }
        }

        return new Grid($ids);
    }

    private function adjust(GdImage $image, int $brightness, int $contrast): void
    {
        if ($brightness !== 0) {
            imagefilter($image, IMG_FILTER_BRIGHTNESS, (int) round($brightness * 2.55));
        }

        if ($contrast !== 0) {
            // GD: negative values increase contrast.
            imagefilter($image, IMG_FILTER_CONTRAST, -$contrast);
        }
    }
}
