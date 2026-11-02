<?php

namespace App\Services\FragmentTool\Reduction;

use App\Services\FragmentTool\PixelBuffer;

/**
 * Detects the big pixels of an already pixelated image, samples their centre, then
 * reduces to cols × rows by dominant colour. Behaves like "mode" when no grid is found.
 */
class GridReducer implements Reducer
{
    public function __construct(
        private readonly GridDetector $detector = new GridDetector,
        private readonly ModeReducer $mode = new ModeReducer,
    ) {}

    public function key(): string
    {
        return 'grid';
    }

    public function label(): string
    {
        return 'Détection auto de la grille';
    }

    public function reduce(PixelBuffer $pixels, int $cols, int $rows): array
    {
        $grid = $this->detector->detect($pixels);

        if ($grid === null) {
            return $this->mode->reduce($pixels, $cols, $rows);
        }

        return $this->mode->reduce($this->detector->sample($pixels, $grid), $cols, $rows);
    }
}
