<?php

namespace App\Services\FragmentTool\Reduction;

use App\Services\FragmentTool\PixelBuffer;

class MeanReducer extends BlockReducer
{
    public function key(): string
    {
        return 'mean';
    }

    public function label(): string
    {
        return 'Moyenne par bloc';
    }

    protected function reduceBlock(PixelBuffer $pixels, array $indexes, int $col, int $row, int $cols, int $rows): array
    {
        return self::average($pixels, $indexes);
    }

    /**
     * @param  list<int>  $indexes
     * @return array{0: int, 1: int, 2: int}
     */
    public static function average(PixelBuffer $pixels, array $indexes): array
    {
        $r = $g = $b = 0;
        foreach ($indexes as $i) {
            $r += $pixels->r[$i];
            $g += $pixels->g[$i];
            $b += $pixels->b[$i];
        }
        $count = max(1, count($indexes));

        return [(int) round($r / $count), (int) round($g / $count), (int) round($b / $count)];
    }
}
