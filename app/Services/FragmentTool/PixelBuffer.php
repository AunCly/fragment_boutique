<?php

namespace App\Services\FragmentTool;

use GdImage;

/**
 * Pixels of a GD image read once into PHP arrays (r, g, b, alpha 0–255).
 */
final class PixelBuffer
{
    /**
     * @param  list<int>  $r
     * @param  list<int>  $g
     * @param  list<int>  $b
     * @param  list<int>  $a
     */
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public array $r,
        public array $g,
        public array $b,
        public array $a,
    ) {}

    public static function fromGd(GdImage $image): self
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $isTrueColor = imageistruecolor($image);
        $r = $g = $b = $a = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                if (! $isTrueColor) {
                    $color = imagecolorsforindex($image, $color);
                    $color = ($color['alpha'] << 24) | ($color['red'] << 16) | ($color['green'] << 8) | $color['blue'];
                }
                $r[] = ($color >> 16) & 0xFF;
                $g[] = ($color >> 8) & 0xFF;
                $b[] = $color & 0xFF;
                $a[] = 255 - (int) round((($color >> 24) & 0x7F) * 255 / 127);
            }
        }

        return new self($width, $height, $r, $g, $b, $a);
    }

    public function index(int $x, int $y): int
    {
        return $y * $this->width + $x;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public function rgb(int $x, int $y): array
    {
        $i = $y * $this->width + $x;

        return [$this->r[$i], $this->g[$i], $this->b[$i]];
    }

    /**
     * Integer pixel bounds of a block of a cols × rows division (floating bounds, at least 1 px).
     *
     * @return array{0: int, 1: int, 2: int, 3: int} x0, y0, x1 (exclusive), y1 (exclusive)
     */
    public function cellBounds(int $col, int $row, int $cols, int $rows): array
    {
        $x0 = (int) floor($col * $this->width / $cols);
        $y0 = (int) floor($row * $this->height / $rows);
        $x1 = max($x0 + 1, (int) floor(($col + 1) * $this->width / $cols));
        $y1 = max($y0 + 1, (int) floor(($row + 1) * $this->height / $rows));

        return [$x0, $y0, min($x1, $this->width), min($y1, $this->height)];
    }

    /**
     * Pixel indexes of a block.
     *
     * @return list<int>
     */
    public function cellIndexes(int $col, int $row, int $cols, int $rows): array
    {
        [$x0, $y0, $x1, $y1] = $this->cellBounds($col, $row, $cols, $rows);
        $indexes = [];

        for ($y = $y0; $y < $y1; $y++) {
            $base = $y * $this->width;
            for ($x = $x0; $x < $x1; $x++) {
                $indexes[] = $base + $x;
            }
        }

        return $indexes;
    }

    public function hasTransparency(): bool
    {
        return min($this->a) < 255;
    }

    /**
     * Composites semi-transparent pixels on a solid colour (alpha becomes 255).
     *
     * @param  array{0: int, 1: int, 2: int}  $background
     */
    public function compositeOn(array $background): self
    {
        $copy = clone $this;

        foreach ($copy->a as $i => $alpha) {
            if ($alpha === 255) {
                continue;
            }
            $t = $alpha / 255;
            $copy->r[$i] = (int) round($copy->r[$i] * $t + $background[0] * (1 - $t));
            $copy->g[$i] = (int) round($copy->g[$i] * $t + $background[1] * (1 - $t));
            $copy->b[$i] = (int) round($copy->b[$i] * $t + $background[2] * (1 - $t));
            $copy->a[$i] = 255;
        }

        return $copy;
    }
}
