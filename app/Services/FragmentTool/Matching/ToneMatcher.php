<?php

namespace App\Services\FragmentTool\Matching;

use App\Services\FragmentTool\Color;
use App\Services\FragmentTool\ToolPalette;

/**
 * "Clarté adaptée": moves the image into the wood colour space before matching.
 * Stretches lightness towards the palette range (blended 50 % with the original),
 * keeps wood-like hues and fades / recentres the others on the mean wood hue,
 * then matches with a 0.5 weight on hue differences against lightness.
 */
class ToneMatcher implements Matcher
{
    private const STRETCH_BLEND = 0.5;

    private const WOOD_HUE_KEEP = 30.0;

    private const WOOD_HUE_FADE = 60.0;

    private const MIN_CHROMA = 10.0;

    private const HUE_WEIGHT = 0.5;

    public function key(): string
    {
        return 'tone';
    }

    public function label(): string
    {
        return 'Clarté adaptée';
    }

    public function match(array $colors, ToolPalette $palette, array $backgroundMask): array
    {
        $exact = [];
        foreach ($palette->all() as $essence) {
            $exact[$essence->hex] = $essence->id;
        }

        $labs = [];
        $subjectLightness = [];
        foreach ($colors as $row => $line) {
            foreach ($line as $col => $rgb) {
                $labs[$row][$col] = Color::rgbToLab($rgb);
                if (! ($backgroundMask[$row][$col] ?? false)) {
                    $subjectLightness[] = $labs[$row][$col][0];
                }
            }
        }

        [$low, $high] = $this->percentiles($subjectLightness !== [] ? $subjectLightness : array_merge(...array_map(fn ($line) => array_column($line, 0), $labs)));
        [$paletteLow, $paletteHigh] = $palette->lightnessRange();
        $woodHue = $this->meanWoodHue($palette);

        $ids = [];
        foreach ($colors as $row => $line) {
            foreach ($line as $col => $rgb) {
                $hex = sprintf('#%02x%02x%02x', ...$rgb);
                if (isset($exact[$hex])) {
                    $ids[$row][$col] = $exact[$hex];

                    continue;
                }

                [$l, $a, $b] = $labs[$row][$col];

                $stretched = $high - $low > 0.001
                    ? $paletteLow + (max($low, min($high, $l)) - $low) / ($high - $low) * ($paletteHigh - $paletteLow)
                    : $l;
                $l = self::STRETCH_BLEND * $stretched + (1 - self::STRETCH_BLEND) * $l;

                [$a, $b] = $this->towardsWood($a, $b, $woodHue);

                $ids[$row][$col] = $this->nearest([$l, $a, $b], $palette);
            }
        }

        return $ids;
    }

    /**
     * @param  list<float>  $values
     * @return array{0: float, 1: float}
     */
    private function percentiles(array $values): array
    {
        sort($values);
        $last = count($values) - 1;

        return [$values[(int) floor($last * 0.02)], $values[(int) ceil($last * 0.98)]];
    }

    private function meanWoodHue(ToolPalette $palette): float
    {
        $a = $b = 0.0;
        foreach ($palette->all() as $essence) {
            $a += $essence->lab[1];
            $b += $essence->lab[2];
        }

        return Color::hueAngle($b, $a);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function towardsWood(float $a, float $b, float $woodHue): array
    {
        $chroma = sqrt($a ** 2 + $b ** 2);
        $hue = Color::hueAngle($b, $a);
        $gap = fmod($hue - $woodHue + 540, 360) - 180;

        if ($chroma >= self::MIN_CHROMA && abs($gap) <= self::WOOD_HUE_KEEP) {
            return [$a, $b];
        }

        $weight = $chroma < self::MIN_CHROMA || abs($gap) >= self::WOOD_HUE_FADE
            ? 1.0
            : (abs($gap) - self::WOOD_HUE_KEEP) / (self::WOOD_HUE_FADE - self::WOOD_HUE_KEEP);

        $chroma *= 1 - 0.5 * $weight;
        $hue = deg2rad($hue - $gap * $weight);

        return [$chroma * cos($hue), $chroma * sin($hue)];
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $lab
     */
    private function nearest(array $lab, ToolPalette $palette): int
    {
        $bestId = 0;
        $bestDistance = INF;

        foreach ($palette->all() as $essence) {
            $distance = ($lab[0] - $essence->lab[0]) ** 2
                + self::HUE_WEIGHT * (($lab[1] - $essence->lab[1]) ** 2 + ($lab[2] - $essence->lab[2]) ** 2);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestId = $essence->id;
            }
        }

        return $bestId;
    }
}
