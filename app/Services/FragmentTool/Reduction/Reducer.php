<?php

namespace App\Services\FragmentTool\Reduction;

use App\Services\FragmentTool\PixelBuffer;

/**
 * Reduces an opaque image to cols × rows colours.
 */
interface Reducer
{
    public function key(): string;

    public function label(): string;

    /**
     * @return list<list<array{0: int, 1: int, 2: int}>> colours indexed [row][col]
     */
    public function reduce(PixelBuffer $pixels, int $cols, int $rows): array;
}
