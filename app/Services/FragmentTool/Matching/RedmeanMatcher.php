<?php

namespace App\Services\FragmentTool\Matching;

use App\Services\FragmentTool\Color;
use App\Services\FragmentTool\Essence;

class RedmeanMatcher extends DistanceMatcher
{
    public function key(): string
    {
        return 'redmean';
    }

    public function label(): string
    {
        return 'RGB pondéré (« redmean »)';
    }

    protected function distance(array $rgb, Essence $essence): float
    {
        return Color::redmeanDistance($rgb, $essence->rgb);
    }
}
