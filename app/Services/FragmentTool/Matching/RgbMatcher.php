<?php

namespace App\Services\FragmentTool\Matching;

use App\Services\FragmentTool\Color;
use App\Services\FragmentTool\Essence;

class RgbMatcher extends DistanceMatcher
{
    public function key(): string
    {
        return 'rgb';
    }

    public function label(): string
    {
        return 'Distance RGB euclidienne';
    }

    protected function distance(array $rgb, Essence $essence): float
    {
        return Color::rgbDistance($rgb, $essence->rgb);
    }
}
