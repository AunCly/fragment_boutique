<?php

namespace App\Services\FragmentTool\Reduction;

use App\Services\FragmentTool\PixelBuffer;

/**
 * Like "mode", but a block crossed by a sharp edge takes the colour of its dark side,
 * which keeps thin strokes (outlines, eyes) that the other methods erase.
 */
class ContoursReducer extends BlockReducer
{
    /** Lightness gap between the darkest and lightest 10 % that counts as an edge. */
    private const EDGE_THRESHOLD = 25.0;

    /** @var list<float> smoothed lightness per pixel */
    private array $lightness = [];

    public function key(): string
    {
        return 'contours';
    }

    public function label(): string
    {
        return 'Contours';
    }

    protected function prepare(PixelBuffer $pixels): void
    {
        $this->lightness = self::smoothedLightness($pixels);
    }

    protected function reduceBlock(PixelBuffer $pixels, array $indexes, int $col, int $row, int $cols, int $rows): array
    {
        $values = array_map(fn (int $i) => $this->lightness[$i], $indexes);
        $sorted = $values;
        sort($sorted);
        $count = count($sorted);
        $low = $sorted[(int) floor(($count - 1) * 0.1)];
        $high = $sorted[(int) ceil(($count - 1) * 0.9)];

        if ($high - $low < self::EDGE_THRESHOLD) {
            return ModeReducer::dominant($pixels, $indexes);
        }

        $middle = ($low + $high) / 2;
        $darkSide = array_values(array_filter($indexes, fn (int $i) => $this->lightness[$i] <= $middle));

        return ModeReducer::dominant($pixels, $darkSide);
    }

    /**
     * Lab lightness of each pixel, smoothed by a 3 × 3 box blur to ignore fine texture.
     *
     * @return list<float>
     */
    public static function smoothedLightness(PixelBuffer $pixels): array
    {
        static $linear = null;
        $linear ??= array_map(static function (int $v): float {
            $c = $v / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, range(0, 255));

        $raw = [];
        foreach ($pixels->r as $i => $red) {
            $y = 0.2126729 * $linear[$red] + 0.7151522 * $linear[$pixels->g[$i]] + 0.0721750 * $linear[$pixels->b[$i]];
            $raw[] = $y > 0.008856 ? 116 * ($y ** (1 / 3)) - 16 : 903.3 * $y;
        }

        $width = $pixels->width;
        $height = $pixels->height;
        $smoothed = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $sum = 0.0;
                $n = 0;
                for ($dy = max(0, $y - 1); $dy <= min($height - 1, $y + 1); $dy++) {
                    for ($dx = max(0, $x - 1); $dx <= min($width - 1, $x + 1); $dx++) {
                        $sum += $raw[$dy * $width + $dx];
                        $n++;
                    }
                }
                $smoothed[] = $sum / $n;
            }
        }

        return $smoothed;
    }
}
