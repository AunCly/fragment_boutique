<?php

namespace App\Services\FragmentTool\Matching;

use App\Services\FragmentTool\Essence;
use App\Services\FragmentTool\ToolPalette;

/**
 * Nearest essence for a colour distance.
 */
abstract class DistanceMatcher implements Matcher
{
    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    abstract protected function distance(array $rgb, Essence $essence): float;

    public function match(array $colors, ToolPalette $palette, array $backgroundMask): array
    {
        $cache = [];
        $ids = [];

        foreach ($colors as $row => $line) {
            foreach ($line as $col => $rgb) {
                $key = ($rgb[0] << 16) | ($rgb[1] << 8) | $rgb[2];
                $ids[$row][$col] = $cache[$key] ??= $this->nearest($rgb, $palette);
            }
        }

        return $ids;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function nearest(array $rgb, ToolPalette $palette): int
    {
        $bestId = 0;
        $bestDistance = INF;

        foreach ($palette->all() as $essence) {
            $distance = $this->distance($rgb, $essence);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestId = $essence->id;
            }
        }

        return $bestId;
    }
}
