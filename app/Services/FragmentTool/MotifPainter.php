<?php

namespace App\Services\FragmentTool;

use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Background motifs, placed by code (never by the AI): free, adjustable at any time
 * and deterministic (fixed seed). Only the background connected to the border is
 * touched, with a one-cell margin around the subject and its halo.
 */
class MotifPainter
{
    public const DENSITIES = ['low' => 'Faible', 'medium' => 'Moyenne'];

    /** @var array<string, string> */
    public const MOTIFS = [
        'none' => 'Aucun',
        'dots' => 'Points',
        'stars' => 'Petites étoiles',
        'confetti' => 'Confettis',
        'hearts' => 'Cœurs',
        'rosettes' => 'Rosaces',
        'diamonds' => 'Losanges',
        'snowflakes' => 'Flocons',
        'diagonals' => 'Diagonales',
        'chevrons' => 'Chevrons',
        'corners' => 'Coins',
        'beaded_frame' => 'Cadre perlé',
        'frame' => 'Cadre plein',
    ];

    /** @var array<string, list<string>> scattered shapes, drawn with "X" */
    private const SHAPES = [
        'dots' => ['X'],
        'stars' => ['.X.', 'XXX', '.X.'],
        'hearts' => ['.X.X.', 'XXXXX', '.XXX.', '..X..'],
        'rosettes' => ['.XXX.', 'X.X.X', 'XX.XX', 'X.X.X', '.XXX.'],
        'diamonds' => ['..X..', '.X.X.', 'X...X', '.X.X.', '..X..'],
        'snowflakes' => ['..X..', 'X.X.X', '.XXX.', 'X.X.X', '..X..'],
    ];

    /** @var list<list<string>> */
    private const CONFETTI = [['X'], ['XX'], ['X', 'X'], ['XX', 'X.'], ['X.', '.X']];

    public function apply(Grid $grid, string $motif, string $density, int $essenceId, int $backgroundId): Grid
    {
        if (! isset(self::MOTIFS[$motif])) {
            throw new InvalidArgumentException("Motif inconnu : {$motif}.");
        }
        if (! isset(self::DENSITIES[$density])) {
            throw new InvalidArgumentException("Densité inconnue : {$density}.");
        }

        if ($motif === 'none' || $essenceId === $backgroundId) {
            return $grid;
        }

        $free = GridMasks::eroded(GridMasks::connectedToBorder($grid, $backgroundId));
        $medium = $density === 'medium';
        $random = new Randomizer(new Mt19937(crc32("{$motif}:{$density}")));

        $cells = match ($motif) {
            'diagonals' => $this->pattern($free, fn (int $r, int $c) => ($r + $c) % ($medium ? 4 : 6) === 0),
            'chevrons' => $this->pattern($free, fn (int $r, int $c) => ($r + abs(($c % 4) - 2)) % ($medium ? 4 : 6) === 0),
            'corners' => $this->corners($free, $medium ? 5 : 3),
            'beaded_frame' => $this->pattern($free, fn (int $r, int $c, int $rows, int $cols) => $this->onRing($r, $c, $rows, $cols) && ($r + $c) % 2 === 0),
            'frame' => $this->pattern($free, fn (int $r, int $c, int $rows, int $cols) => $this->onRing($r, $c, $rows, $cols)),
            'confetti' => $this->scatter($free, fn () => self::CONFETTI[$random->getInt(0, count(self::CONFETTI) - 1)], $medium ? 4 : 6, $random),
            default => $this->scatter($free, fn () => self::SHAPES[$motif], $this->spacing($motif, $medium), $random),
        };

        $result = $grid->cells;
        foreach ($cells as [$row, $col]) {
            $result[$row][$col] = $essenceId;
        }

        return new Grid($result);
    }

    private function spacing(string $motif, bool $medium): int
    {
        $size = max(count(self::SHAPES[$motif]), strlen(self::SHAPES[$motif][0]));

        return $size + ($medium ? 2 : 4);
    }

    /**
     * @param  list<list<bool>>  $free
     * @param  callable(int, int, int, int): bool  $isOn
     * @return list<array{0: int, 1: int}>
     */
    private function pattern(array $free, callable $isOn): array
    {
        $rows = count($free);
        $cols = count($free[0]);
        $cells = [];

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                if ($free[$row][$col] && $isOn($row, $col, $rows, $cols)) {
                    $cells[] = [$row, $col];
                }
            }
        }

        return $cells;
    }

    private function onRing(int $row, int $col, int $rows, int $cols): bool
    {
        return $row === 0 || $col === 0 || $row === $rows - 1 || $col === $cols - 1;
    }

    /**
     * L-shaped ornaments in the four corners.
     *
     * @param  list<list<bool>>  $free
     * @return list<array{0: int, 1: int}>
     */
    private function corners(array $free, int $length): array
    {
        $rows = count($free);
        $cols = count($free[0]);
        $cells = [];

        foreach ([[0, 0, 1, 1], [0, $cols - 1, 1, -1], [$rows - 1, 0, -1, 1], [$rows - 1, $cols - 1, -1, -1]] as [$row, $col, $dy, $dx]) {
            for ($k = 0; $k < $length; $k++) {
                foreach ([[$row + $dy * $k, $col], [$row, $col + $dx * $k]] as [$r, $c]) {
                    if ($r >= 0 && $c >= 0 && $r < $rows && $c < $cols && $free[$r][$c]) {
                        $cells["{$r}:{$c}"] = [$r, $c];
                    }
                }
            }
        }

        return array_values($cells);
    }

    /**
     * Whole shapes on a jittered lattice; a shape is placed only if all its cells are
     * free and it does not touch an already placed one.
     *
     * @param  list<list<bool>>  $free
     * @param  callable(): list<string>  $nextShape
     * @return list<array{0: int, 1: int}>
     */
    private function scatter(array $free, callable $nextShape, int $spacing, Randomizer $random): array
    {
        $rows = count($free);
        $cols = count($free[0]);
        $taken = [];
        $cells = [];
        $jitter = max(0, intdiv($spacing, 3));

        for ($y = 0; $y < $rows; $y += $spacing) {
            $shift = (intdiv($y, $spacing) % 2) * intdiv($spacing, 2);
            for ($x = $shift; $x < $cols; $x += $spacing) {
                $shape = $nextShape();
                $top = $y + $random->getInt(0, $jitter);
                $left = $x + $random->getInt(0, $jitter);
                $shapeCells = [];

                foreach ($shape as $dy => $line) {
                    foreach (str_split($line) as $dx => $char) {
                        if ($char === 'X') {
                            $shapeCells[] = [$top + $dy, $left + $dx];
                        }
                    }
                }

                if (! $this->fits($shapeCells, $free, $taken)) {
                    continue;
                }

                foreach ($shapeCells as [$r, $c]) {
                    $taken["{$r}:{$c}"] = true;
                    $cells[] = [$r, $c];
                }
            }
        }

        return $cells;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $shapeCells
     * @param  list<list<bool>>  $free
     * @param  array<string, bool>  $taken
     */
    private function fits(array $shapeCells, array $free, array $taken): bool
    {
        foreach ($shapeCells as [$r, $c]) {
            if (! ($free[$r][$c] ?? false)) {
                return false;
            }
            foreach (GridMasks::NEIGHBOURS_8 as [$dy, $dx]) {
                if (isset($taken[($r + $dy).':'.($c + $dx)])) {
                    return false;
                }
            }
        }

        return true;
    }
}
