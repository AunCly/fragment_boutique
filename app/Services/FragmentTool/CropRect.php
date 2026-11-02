<?php

namespace App\Services\FragmentTool;

use InvalidArgumentException;

/**
 * Area of the source image kept, in source pixel coordinates.
 */
final readonly class CropRect
{
    /** Allowed gap between the crop ratio and the format ratio (rounding in the browser). */
    private const RATIO_TOLERANCE = 0.03;

    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {}

    /**
     * Checks the crop against the image size and the format: inside the image,
     * same ratio as the format, at least one source pixel per cell.
     */
    public function validateFor(int $imageWidth, int $imageHeight, int $cols, int $rows): void
    {
        if ($this->x < 0 || $this->y < 0 || $this->x + $this->width > $imageWidth || $this->y + $this->height > $imageHeight) {
            throw new InvalidArgumentException('Le recadrage dépasse de l\'image.');
        }

        if ($this->width < $cols || $this->height < $rows) {
            throw new InvalidArgumentException("Le recadrage doit faire au moins {$cols} × {$rows} pixels.");
        }

        $expected = $cols / $rows;
        $actual = $this->width / $this->height;

        if (abs($actual - $expected) / $expected > self::RATIO_TOLERANCE) {
            throw new InvalidArgumentException("Le recadrage doit avoir les proportions du format {$cols} × {$rows}.");
        }
    }

    /**
     * Largest centred crop with the format ratio (default crop).
     */
    public static function centred(int $imageWidth, int $imageHeight, int $cols, int $rows): self
    {
        $scale = min($imageWidth / $cols, $imageHeight / $rows);
        $width = (int) floor($cols * $scale);
        $height = (int) floor($rows * $scale);

        return new self(intdiv($imageWidth - $width, 2), intdiv($imageHeight - $height, 2), $width, $height);
    }
}
