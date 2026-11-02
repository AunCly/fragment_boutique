<?php

namespace App\Services\FragmentTool;

/**
 * Boolean masks over a grid (background connected to the border, neighbourhoods).
 */
class GridMasks
{
    /** @var list<array{0: int, 1: int}> */
    public const NEIGHBOURS_8 = [[-1, -1], [-1, 0], [-1, 1], [0, -1], [0, 1], [1, -1], [1, 0], [1, 1]];

    /** @var list<array{0: int, 1: int}> */
    public const NEIGHBOURS_4 = [[-1, 0], [1, 0], [0, -1], [0, 1]];

    /**
     * Most frequent essence on the border of the grid.
     */
    public static function borderMajority(Grid $grid): int
    {
        $border = [];
        for ($row = 0; $row < $grid->rows; $row++) {
            for ($col = 0; $col < $grid->cols; $col++) {
                if ($row === 0 || $col === 0 || $row === $grid->rows - 1 || $col === $grid->cols - 1) {
                    $border[] = $grid->get($row, $col);
                }
            }
        }

        $counts = array_count_values($border);
        arsort($counts);

        return (int) array_key_first($counts);
    }

    /**
     * Cells of the given essence connected to the border (4-connectivity).
     *
     * @return list<list<bool>>
     */
    public static function connectedToBorder(Grid $grid, int $id): array
    {
        $mask = array_fill(0, $grid->rows, array_fill(0, $grid->cols, false));
        $queue = [];

        for ($row = 0; $row < $grid->rows; $row++) {
            for ($col = 0; $col < $grid->cols; $col++) {
                $onBorder = $row === 0 || $col === 0 || $row === $grid->rows - 1 || $col === $grid->cols - 1;
                if ($onBorder && $grid->get($row, $col) === $id) {
                    $mask[$row][$col] = true;
                    $queue[] = [$row, $col];
                }
            }
        }

        while ($queue !== []) {
            [$row, $col] = array_pop($queue);
            foreach (self::NEIGHBOURS_4 as [$dy, $dx]) {
                $r = $row + $dy;
                $c = $col + $dx;
                if ($grid->inBounds($r, $c) && ! $mask[$r][$c] && $grid->get($r, $c) === $id) {
                    $mask[$r][$c] = true;
                    $queue[] = [$r, $c];
                }
            }
        }

        return $mask;
    }

    /**
     * Mask cells whose 8 neighbours are all in the mask (or outside the grid):
     * leaves a one-cell margin around everything that is not in the mask.
     *
     * @param  list<list<bool>>  $mask
     * @return list<list<bool>>
     */
    public static function eroded(array $mask): array
    {
        $rows = count($mask);
        $cols = count($mask[0]);
        $eroded = $mask;

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                if (! $mask[$row][$col]) {
                    continue;
                }
                foreach (self::NEIGHBOURS_8 as [$dy, $dx]) {
                    $r = $row + $dy;
                    $c = $col + $dx;
                    if ($r >= 0 && $c >= 0 && $r < $rows && $c < $cols && ! $mask[$r][$c]) {
                        $eroded[$row][$col] = false;
                        break;
                    }
                }
            }
        }

        return $eroded;
    }
}
