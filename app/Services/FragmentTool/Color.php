<?php

namespace App\Services\FragmentTool;

/**
 * sRGB / CIE Lab conversions and colour distances.
 */
class Color
{
    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * @param  array{0: int|float, 1: int|float, 2: int|float}  $rgb
     * @return array{0: float, 1: float, 2: float}
     */
    public static function rgbToLab(array $rgb): array
    {
        [$r, $g, $b] = array_map(static function (int|float $channel): float {
            $c = $channel / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        $x = ($r * 0.4124564 + $g * 0.3575761 + $b * 0.1804375) / 0.95047;
        $y = ($r * 0.2126729 + $g * 0.7151522 + $b * 0.0721750);
        $z = ($r * 0.0193339 + $g * 0.1191920 + $b * 0.9503041) / 1.08883;

        $f = static fn (float $t): float => $t > 0.008856 ? $t ** (1 / 3) : (7.787 * $t) + (16 / 116);

        $fx = $f($x);
        $fy = $f($y);
        $fz = $f($z);

        return [(116 * $fy) - 16, 500 * ($fx - $fy), 200 * ($fy - $fz)];
    }

    /**
     * WCAG relative luminance, from 0 (black) to 1 (white).
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    public static function luminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Perceptual lightness (Lab L) of an sRGB colour, from 0 to 100.
     *
     * @param  array{0: int|float, 1: int|float, 2: int|float}  $rgb
     */
    public static function lightness(array $rgb): float
    {
        return self::rgbToLab($rgb)[0];
    }

    /**
     * @param  array{0: int|float, 1: int|float, 2: int|float}  $a
     * @param  array{0: int|float, 1: int|float, 2: int|float}  $b
     */
    public static function rgbDistance(array $a, array $b): float
    {
        return ($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2 + ($a[2] - $b[2]) ** 2;
    }

    /**
     * @param  array{0: int|float, 1: int|float, 2: int|float}  $a
     * @param  array{0: int|float, 1: int|float, 2: int|float}  $b
     */
    public static function redmeanDistance(array $a, array $b): float
    {
        $redMean = ($a[0] + $b[0]) / 2;

        return (2 + $redMean / 256) * ($a[0] - $b[0]) ** 2
            + 4 * ($a[1] - $b[1]) ** 2
            + (2 + (255 - $redMean) / 256) * ($a[2] - $b[2]) ** 2;
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $a
     * @param  array{0: float, 1: float, 2: float}  $b
     */
    public static function deltaE76(array $a, array $b): float
    {
        return ($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2 + ($a[2] - $b[2]) ** 2;
    }

    /**
     * CIEDE2000 colour difference between two Lab colours.
     *
     * @param  array{0: float, 1: float, 2: float}  $lab1
     * @param  array{0: float, 1: float, 2: float}  $lab2
     */
    public static function ciede2000(array $lab1, array $lab2): float
    {
        [$l1, $a1, $b1] = $lab1;
        [$l2, $a2, $b2] = $lab2;

        $c1 = sqrt($a1 ** 2 + $b1 ** 2);
        $c2 = sqrt($a2 ** 2 + $b2 ** 2);
        $cMean = ($c1 + $c2) / 2;
        $g = 0.5 * (1 - sqrt($cMean ** 7 / ($cMean ** 7 + 25 ** 7)));

        $a1p = (1 + $g) * $a1;
        $a2p = (1 + $g) * $a2;
        $c1p = sqrt($a1p ** 2 + $b1 ** 2);
        $c2p = sqrt($a2p ** 2 + $b2 ** 2);
        $h1p = self::hueAngle($b1, $a1p);
        $h2p = self::hueAngle($b2, $a2p);

        $deltaLp = $l2 - $l1;
        $deltaCp = $c2p - $c1p;

        $deltahp = 0.0;
        if ($c1p * $c2p != 0) {
            $deltahp = $h2p - $h1p;
            if ($deltahp > 180) {
                $deltahp -= 360;
            } elseif ($deltahp < -180) {
                $deltahp += 360;
            }
        }
        $deltaHp = 2 * sqrt($c1p * $c2p) * sin(deg2rad($deltahp / 2));

        $lMean = ($l1 + $l2) / 2;
        $cMeanP = ($c1p + $c2p) / 2;

        $hMeanP = $h1p + $h2p;
        if ($c1p * $c2p != 0) {
            if (abs($h1p - $h2p) <= 180) {
                $hMeanP = ($h1p + $h2p) / 2;
            } elseif ($h1p + $h2p < 360) {
                $hMeanP = ($h1p + $h2p + 360) / 2;
            } else {
                $hMeanP = ($h1p + $h2p - 360) / 2;
            }
        }

        $t = 1
            - 0.17 * cos(deg2rad($hMeanP - 30))
            + 0.24 * cos(deg2rad(2 * $hMeanP))
            + 0.32 * cos(deg2rad(3 * $hMeanP + 6))
            - 0.20 * cos(deg2rad(4 * $hMeanP - 63));

        $deltaTheta = 30 * exp(-((($hMeanP - 275) / 25) ** 2));
        $rc = 2 * sqrt($cMeanP ** 7 / ($cMeanP ** 7 + 25 ** 7));
        $sl = 1 + (0.015 * ($lMean - 50) ** 2) / sqrt(20 + ($lMean - 50) ** 2);
        $sc = 1 + 0.045 * $cMeanP;
        $sh = 1 + 0.015 * $cMeanP * $t;
        $rt = -sin(deg2rad(2 * $deltaTheta)) * $rc;

        return sqrt(
            ($deltaLp / $sl) ** 2
            + ($deltaCp / $sc) ** 2
            + ($deltaHp / $sh) ** 2
            + $rt * ($deltaCp / $sc) * ($deltaHp / $sh)
        );
    }

    /**
     * Hue angle in degrees (0–360) of a Lab colour.
     */
    public static function hueAngle(float $b, float $a): float
    {
        if ($a == 0 && $b == 0) {
            return 0.0;
        }

        $angle = rad2deg(atan2($b, $a));

        return $angle < 0 ? $angle + 360 : $angle;
    }
}
