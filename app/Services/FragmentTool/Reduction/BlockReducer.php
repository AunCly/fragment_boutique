<?php

namespace App\Services\FragmentTool\Reduction;

use App\Services\FragmentTool\PixelBuffer;

/**
 * Base for reducers that compute each cell from the pixels of its block.
 */
abstract class BlockReducer implements Reducer
{
    /**
     * @param  list<int>  $indexes
     * @return array{0: int, 1: int, 2: int}
     */
    abstract protected function reduceBlock(PixelBuffer $pixels, array $indexes, int $col, int $row, int $cols, int $rows): array;

    protected function prepare(PixelBuffer $pixels): void {}

    public function reduce(PixelBuffer $pixels, int $cols, int $rows): array
    {
        $this->prepare($pixels);
        $colors = [];

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $colors[$row][$col] = $this->reduceBlock($pixels, $pixels->cellIndexes($col, $row, $cols, $rows), $col, $row, $cols, $rows);
            }
        }

        return $colors;
    }
}
