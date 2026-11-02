<?php

namespace App\Services\FragmentTool\Reduction;

use App\Services\FragmentTool\PixelBuffer;

class ModeReducer extends BlockReducer
{
    /** Bits dropped per channel to group close shades before counting. */
    private const QUANTIZE_SHIFT = 3;

    public function key(): string
    {
        return 'mode';
    }

    public function label(): string
    {
        return 'Couleur majoritaire';
    }

    protected function reduceBlock(PixelBuffer $pixels, array $indexes, int $col, int $row, int $cols, int $rows): array
    {
        return self::dominant($pixels, $indexes);
    }

    /**
     * Average colour of the most frequent quantized bucket.
     *
     * @param  list<int>  $indexes
     * @return array{0: int, 1: int, 2: int}
     */
    public static function dominant(PixelBuffer $pixels, array $indexes): array
    {
        $buckets = [];
        $shift = self::QUANTIZE_SHIFT;

        foreach ($indexes as $i) {
            $key = (($pixels->r[$i] >> $shift) << 16) | (($pixels->g[$i] >> $shift) << 8) | ($pixels->b[$i] >> $shift);
            $buckets[$key][] = $i;
        }

        $best = [];
        foreach ($buckets as $members) {
            if (count($members) > count($best)) {
                $best = $members;
            }
        }

        return MeanReducer::average($pixels, $best);
    }
}
