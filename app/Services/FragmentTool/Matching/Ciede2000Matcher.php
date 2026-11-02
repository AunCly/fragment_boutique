<?php

namespace App\Services\FragmentTool\Matching;

use App\Services\FragmentTool\Color;
use App\Services\FragmentTool\Essence;

class Ciede2000Matcher extends DistanceMatcher
{
    public function key(): string
    {
        return 'ciede2000';
    }

    public function label(): string
    {
        return 'Distance CIEDE2000';
    }

    protected function distance(array $rgb, Essence $essence): float
    {
        return Color::ciede2000(Color::rgbToLab($rgb), $essence->lab);
    }
}
