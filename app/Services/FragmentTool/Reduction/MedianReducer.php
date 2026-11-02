<?php

namespace App\Services\FragmentTool\Reduction;

use App\Services\FragmentTool\PixelBuffer;

class MedianReducer extends BlockReducer
{
    public function key(): string
    {
        return 'median';
    }

    public function label(): string
    {
        return 'Médiane par bloc';
    }

    protected function reduceBlock(PixelBuffer $pixels, array $indexes, int $col, int $row, int $cols, int $rows): array
    {
        $median = static function (array $values): int {
            sort($values);

            return $values[intdiv(count($values), 2)];
        };

        return [
            $median(array_map(fn (int $i) => $pixels->r[$i], $indexes)),
            $median(array_map(fn (int $i) => $pixels->g[$i], $indexes)),
            $median(array_map(fn (int $i) => $pixels->b[$i], $indexes)),
        ];
    }
}
