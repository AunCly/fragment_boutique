<?php

namespace App\Services\FragmentTool\Reduction;

use App\Services\FragmentTool\PixelBuffer;

class NearestReducer extends BlockReducer
{
    public function key(): string
    {
        return 'nearest';
    }

    public function label(): string
    {
        return 'Plus proche voisin';
    }

    protected function reduceBlock(PixelBuffer $pixels, array $indexes, int $col, int $row, int $cols, int $rows): array
    {
        [$x0, $y0, $x1, $y1] = $pixels->cellBounds($col, $row, $cols, $rows);

        return $pixels->rgb(intdiv($x0 + $x1 - 1, 2), intdiv($y0 + $y1 - 1, 2));
    }
}
