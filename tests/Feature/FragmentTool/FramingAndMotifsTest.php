<?php

namespace Tests\Feature\FragmentTool;

use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\GridMasks;
use App\Services\FragmentTool\MotifPainter;
use App\Services\FragmentTool\SubjectFramer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FramingAndMotifsTest extends TestCase
{
    use RefreshDatabase;

    private const BACKGROUND = 2;

    private const HALO = 1;

    private const SUBJECT = 7;

    private const MOTIF = 5;

    private const OTHER_BACKGROUND = 3;

    /**
     * Square subject in the middle of a uniform background.
     */
    private function subjectGrid(int $size = 16, int $margin = 6, int $background = self::BACKGROUND): Grid
    {
        $cells = [];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                $inside = $r >= $margin && $c >= $margin && $r < $size - $margin && $c < $size - $margin;
                $cells[$r][$c] = $inside ? self::SUBJECT : $background;
            }
        }

        return new Grid($cells);
    }

    public function test_border_background_is_the_connected_border_majority(): void
    {
        $grid = $this->subjectGrid();
        $mask = GridMasks::connectedToBorder($grid, GridMasks::borderMajority($grid));

        $this->assertSame(self::BACKGROUND, GridMasks::borderMajority($grid));
        $this->assertTrue($mask[0][0]);
        $this->assertFalse($mask[8][8]);
    }

    public function test_framing_replaces_the_background_and_adds_a_halo(): void
    {
        $framed = (new SubjectFramer)->frame($this->subjectGrid(background: self::OTHER_BACKGROUND), self::BACKGROUND, self::HALO);

        $this->assertSame(self::BACKGROUND, $framed->get(0, 0));
        $this->assertSame(self::HALO, $framed->get(5, 8));
        $this->assertSame(self::HALO, $framed->get(5, 5), 'Diagonals are part of the halo.');
        $this->assertSame(self::SUBJECT, $framed->get(6, 6));
        $this->assertSame(self::BACKGROUND, $framed->get(4, 8));
        $this->assertArrayNotHasKey(self::OTHER_BACKGROUND, $framed->counts());
    }

    public function test_framing_does_not_double_an_existing_halo(): void
    {
        $cells = $this->subjectGrid()->cells;
        for ($r = 5; $r <= 10; $r++) {
            for ($c = 5; $c <= 10; $c++) {
                if ($r === 5 || $c === 5 || $r === 10 || $c === 10) {
                    $cells[$r][$c] = self::HALO;
                }
            }
        }

        $framed = (new SubjectFramer)->frame(new Grid($cells), self::BACKGROUND, self::HALO);

        $this->assertSame(self::HALO, $framed->get(5, 8));
        $this->assertSame(self::BACKGROUND, $framed->get(4, 8));
    }

    public function test_no_halo_when_the_background_is_the_halo_essence(): void
    {
        $framed = (new SubjectFramer)->frame($this->subjectGrid(), self::HALO, self::HALO);

        $this->assertSame([self::HALO => 256 - 16, self::SUBJECT => 16], $framed->counts());
    }

    public function test_no_halo_without_halo_essence(): void
    {
        $framed = (new SubjectFramer)->frame($this->subjectGrid(background: self::OTHER_BACKGROUND), self::BACKGROUND, null);

        $this->assertSame([self::BACKGROUND => 256 - 16, self::SUBJECT => 16], $framed->counts());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function motifs(): array
    {
        $motifs = array_keys(array_diff_key(MotifPainter::MOTIFS, ['none' => true]));

        return array_combine($motifs, array_map(fn (string $motif) => [$motif], $motifs));
    }

    #[DataProvider('motifs')]
    public function test_motifs_only_touch_free_background_cells(string $motif): void
    {
        $grid = (new SubjectFramer)->frame($this->subjectGrid(32, 12), self::BACKGROUND, self::HALO);
        $free = GridMasks::eroded(GridMasks::connectedToBorder($grid, self::BACKGROUND));

        $result = (new MotifPainter)->apply($grid, $motif, 'medium', self::MOTIF, self::BACKGROUND);

        $changed = 0;
        foreach ($result->cells as $r => $row) {
            foreach ($row as $c => $id) {
                if ($id !== $grid->get($r, $c)) {
                    $changed++;
                    $this->assertTrue($free[$r][$c], "{$motif} touched cell {$r}:{$c} outside the free background.");
                    $this->assertSame(self::MOTIF, $id);
                }
            }
        }

        $this->assertGreaterThan(0, $changed, "{$motif} placed nothing on a 32 × 32 grid.");
    }

    public function test_motifs_keep_a_margin_around_subject_and_halo(): void
    {
        $grid = (new SubjectFramer)->frame($this->subjectGrid(32, 12), self::BACKGROUND, self::HALO);
        $result = (new MotifPainter)->apply($grid, 'dots', 'medium', self::MOTIF, self::BACKGROUND);

        foreach ($result->cells as $r => $row) {
            foreach ($row as $c => $id) {
                if ($id !== self::MOTIF) {
                    continue;
                }
                foreach (GridMasks::NEIGHBOURS_8 as [$dy, $dx]) {
                    $neighbour = $result->cells[$r + $dy][$c + $dx] ?? self::BACKGROUND;
                    $this->assertNotContains($neighbour, [self::HALO, self::SUBJECT]);
                }
            }
        }
    }

    public function test_scattered_drawings_are_placed_whole(): void
    {
        $grid = Grid::filled(32, 32, self::BACKGROUND);
        $result = (new MotifPainter)->apply($grid, 'stars', 'low', self::MOTIF, self::BACKGROUND);
        $motifCells = $result->counts()[self::MOTIF] ?? 0;

        $this->assertGreaterThan(0, $motifCells);
        $this->assertSame(0, $motifCells % 5, 'Every star has its 5 cells.');
    }

    public function test_medium_density_is_busier_than_low(): void
    {
        $grid = Grid::filled(32, 32, self::BACKGROUND);
        $painter = new MotifPainter;

        foreach (['dots', 'hearts', 'diagonals', 'confetti'] as $motif) {
            $low = $painter->apply($grid, $motif, 'low', self::MOTIF, self::BACKGROUND)->counts()[self::MOTIF] ?? 0;
            $medium = $painter->apply($grid, $motif, 'medium', self::MOTIF, self::BACKGROUND)->counts()[self::MOTIF] ?? 0;
            $this->assertGreaterThan($low, $medium, $motif);
        }
    }

    public function test_frame_is_symmetric(): void
    {
        $result = (new MotifPainter)->apply(Grid::filled(16, 16, self::BACKGROUND), 'frame', 'low', self::MOTIF, self::BACKGROUND);

        $this->assertSame($result->cells, array_reverse($result->cells));
        $this->assertSame($result->cells, array_map('array_reverse', $result->cells));
        $this->assertSame(60, $result->counts()[self::MOTIF]);
    }

    public function test_motifs_are_deterministic(): void
    {
        $grid = Grid::filled(32, 32, self::BACKGROUND);
        $painter = new MotifPainter;

        $this->assertSame(
            $painter->apply($grid, 'confetti', 'medium', self::MOTIF, self::BACKGROUND)->cells,
            $painter->apply($grid, 'confetti', 'medium', self::MOTIF, self::BACKGROUND)->cells,
        );
    }

    public function test_none_and_same_essence_leave_the_grid_unchanged(): void
    {
        $grid = $this->subjectGrid();
        $painter = new MotifPainter;

        $this->assertSame($grid->cells, $painter->apply($grid, 'none', 'low', self::MOTIF, self::BACKGROUND)->cells);
        $this->assertSame($grid->cells, $painter->apply($grid, 'dots', 'low', self::BACKGROUND, self::BACKGROUND)->cells);
    }

    public function test_unknown_motif_or_density_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MotifPainter)->apply($this->subjectGrid(), 'licorne', 'low', self::MOTIF, self::BACKGROUND);
    }
}
