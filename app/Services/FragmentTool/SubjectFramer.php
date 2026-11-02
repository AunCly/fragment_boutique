<?php

namespace App\Services\FragmentTool;

/**
 * Imposes the background and the halo after each AI projection (frame_subject):
 * the background is the border-majority essence connected to the border; it becomes
 * the background essence, and its cells touching the subject (diagonals included)
 * become the halo essence. A halo already drawn is not doubled.
 */
class SubjectFramer
{
    public function frame(Grid $grid, int $backgroundId, int $haloId): Grid
    {
        $mask = GridMasks::connectedToBorder($grid, GridMasks::borderMajority($grid));
        $cells = $grid->cells;

        foreach ($mask as $row => $line) {
            foreach ($line as $col => $isBackground) {
                if ($isBackground) {
                    $cells[$row][$col] = $backgroundId;
                }
            }
        }

        if ($backgroundId === $haloId) {
            return new Grid($cells);
        }

        $framed = $cells;
        foreach ($mask as $row => $line) {
            foreach ($line as $col => $isBackground) {
                if (! $isBackground) {
                    continue;
                }
                foreach (GridMasks::NEIGHBOURS_8 as [$dy, $dx]) {
                    $r = $row + $dy;
                    $c = $col + $dx;
                    if ($grid->inBounds($r, $c) && ! $mask[$r][$c] && $cells[$r][$c] !== $haloId) {
                        $framed[$row][$col] = $haloId;
                        break;
                    }
                }
            }
        }

        return new Grid($framed);
    }
}
