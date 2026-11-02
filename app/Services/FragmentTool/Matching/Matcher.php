<?php

namespace App\Services\FragmentTool\Matching;

use App\Services\FragmentTool\ToolPalette;

/**
 * Associates reduced colours with the palette essences.
 */
interface Matcher
{
    public function key(): string;

    public function label(): string;

    /**
     * @param  list<list<array{0: int, 1: int, 2: int}>>  $colors  [row][col]
     * @param  list<list<bool>>  $backgroundMask  cells that will receive the background essence (excluded from statistics)
     * @return list<list<int>> essence ids [row][col]
     */
    public function match(array $colors, ToolPalette $palette, array $backgroundMask): array;
}
