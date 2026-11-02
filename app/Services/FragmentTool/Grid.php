<?php

namespace App\Services\FragmentTool;

use InvalidArgumentException;

/**
 * A Fragment grid: `rows` lines of `cols` essence ids. Every render is computed from it.
 */
final class Grid
{
    public readonly int $rows;

    public readonly int $cols;

    /**
     * @param  list<list<int>>  $cells
     */
    public function __construct(public array $cells)
    {
        $this->rows = count($cells);
        $this->cols = $this->rows > 0 ? count($cells[0]) : 0;

        if ($this->rows === 0 || $this->cols === 0) {
            throw new InvalidArgumentException('La grille est vide.');
        }

        foreach ($cells as $row) {
            if (count($row) !== $this->cols) {
                throw new InvalidArgumentException('Toutes les lignes de la grille doivent avoir la même longueur.');
            }
        }
    }

    public static function filled(int $cols, int $rows, int $id): self
    {
        return new self(array_fill(0, $rows, array_fill(0, $cols, $id)));
    }

    /**
     * Validates a grid sent by the browser (parse_grid): expected size and known essences.
     */
    public static function parse(mixed $data, int $cols, int $rows, ToolPalette $palette): self
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (! is_array($data) || ! array_is_list($data)) {
            throw new InvalidArgumentException('Grille illisible.');
        }

        $cells = [];
        foreach ($data as $row) {
            if (! is_array($row) || ! array_is_list($row)) {
                throw new InvalidArgumentException('Grille illisible.');
            }
            $cells[] = array_map(function (mixed $id) use ($palette): int {
                if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                    throw new InvalidArgumentException('Grille illisible.');
                }
                if (! $palette->has((int) $id)) {
                    throw new InvalidArgumentException("Essence inconnue dans la grille : {$id}.");
                }

                return (int) $id;
            }, $row);
        }

        $grid = new self($cells);

        if ($grid->cols !== $cols || $grid->rows !== $rows) {
            throw new InvalidArgumentException("La grille doit faire {$cols} × {$rows} cases.");
        }

        return $grid;
    }

    public function get(int $row, int $col): int
    {
        return $this->cells[$row][$col];
    }

    public function with(int $row, int $col, int $id): self
    {
        $cells = $this->cells;
        $cells[$row][$col] = $id;

        return new self($cells);
    }

    public function inBounds(int $row, int $col): bool
    {
        return $row >= 0 && $col >= 0 && $row < $this->rows && $col < $this->cols;
    }

    /**
     * Number of pieces per essence id, most used first.
     *
     * @return array<int, int>
     */
    public function counts(): array
    {
        $counts = array_count_values(array_merge(...$this->cells));
        arsort($counts);

        return $counts;
    }

    public function equals(self $other): bool
    {
        return $this->cells === $other->cells;
    }

    /**
     * @return list<list<int>>
     */
    public function toArray(): array
    {
        return $this->cells;
    }
}
