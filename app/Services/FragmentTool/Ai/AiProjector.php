<?php

namespace App\Services\FragmentTool\Ai;

use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\Matching\Ciede2000Matcher;
use App\Services\FragmentTool\PixelBuffer;
use App\Services\FragmentTool\Reduction\ContoursReducer;
use App\Services\FragmentTool\Reduction\GridDetector;
use App\Services\FragmentTool\Reduction\ModeReducer;
use App\Services\FragmentTool\SourceImage;
use App\Services\FragmentTool\SubjectFramer;
use App\Services\FragmentTool\ToolPalette;
use GdImage;

/**
 * Projects an image drawn by the model onto the Fragment grid and essences (reduce_ai):
 * grid detection with square cells, "contours" logic when the model drew finer than
 * asked, CIEDE2000 matching, then frame_subject.
 */
class AiProjector
{
    /** A detected grid within this share of the target size is used as is. */
    private const SIZE_TOLERANCE = 0.2;

    /** Periods this close on both axes are unified into square cells. */
    private const SQUARE_TOLERANCE = 0.2;

    public function __construct(
        private readonly GridDetector $detector = new GridDetector,
        private readonly ModeReducer $mode = new ModeReducer,
        private readonly ContoursReducer $contours = new ContoursReducer,
        private readonly Ciede2000Matcher $matcher = new Ciede2000Matcher,
        private readonly SubjectFramer $framer = new SubjectFramer,
    ) {}

    public function project(string $imageBytes, int $cols, int $rows, ToolPalette $palette, int $backgroundId, int $haloId): Grid
    {
        $image = @imagecreatefromstring($imageBytes);
        if ($image === false) {
            throw new GeminiException('Image renvoyée par Gemini illisible.');
        }

        $pixels = PixelBuffer::fromGd($this->toFormatRatio($image, $cols, $rows));
        $colors = $this->reduce($pixels, $cols, $rows);
        $noBackground = array_fill(0, $rows, array_fill(0, $cols, false));
        $grid = new Grid($this->matcher->match($colors, $palette, $noBackground));

        return $this->framer->frame($grid, $backgroundId, $haloId);
    }

    /**
     * @return list<list<array{0: int, 1: int, 2: int}>>
     */
    private function reduce(PixelBuffer $pixels, int $cols, int $rows): array
    {
        $grid = $this->detector->detect($pixels, $cols, $rows);

        if ($grid === null) {
            return $this->contours->reduce($pixels, $cols, $rows);
        }

        $px = $grid['x']['period'];
        $py = $grid['y']['period'];
        if (abs($px - $py) / max($px, $py) <= self::SQUARE_TOLERANCE) {
            $grid['x']['period'] = $grid['y']['period'] = ($px + $py) / 2;
        }

        $detectedCols = $pixels->width / $grid['x']['period'];
        $detectedRows = $pixels->height / $grid['y']['period'];

        if ($detectedCols > $cols * (1 + self::SIZE_TOLERANCE) || $detectedRows > $rows * (1 + self::SIZE_TOLERANCE)) {
            return $this->contours->reduce($pixels, $cols, $rows);
        }

        return $this->mode->reduce($this->detector->sample($pixels, $grid), $cols, $rows);
    }

    /**
     * Stretches the model output to the exact format ratio (it only supports a few ratios).
     */
    private function toFormatRatio(GdImage $image, int $cols, int $rows): GdImage
    {
        $longSide = min(config('fragment_tool.ai.projection_long_side'), max(imagesx($image), imagesy($image)));
        $width = $cols >= $rows ? $longSide : (int) round($longSide * $cols / $rows);
        $height = $rows >= $cols ? $longSide : (int) round($longSide * $rows / $cols);

        if ($width === imagesx($image) && $height === imagesy($image)) {
            return $image;
        }

        $target = SourceImage::blank($width, $height);
        imagecopyresampled($target, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $target;
    }
}
