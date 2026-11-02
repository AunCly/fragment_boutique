<?php

namespace App\Services\FragmentTool\Matching;

use App\Services\FragmentTool\Color;
use App\Services\FragmentTool\Essence;

class LabMatcher extends DistanceMatcher
{
    public function key(): string
    {
        return 'lab';
    }

    public function label(): string
    {
        return 'Distance CIE76 en espace Lab';
    }

    protected function distance(array $rgb, Essence $essence): float
    {
        return Color::deltaE76(Color::rgbToLab($rgb), $essence->lab);
    }
}
