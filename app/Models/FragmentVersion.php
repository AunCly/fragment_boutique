<?php

namespace App\Models;

use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\MotifPainter;
use App\Services\FragmentTool\ToolPalette;
use Database\Factories\FragmentVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * A grid produced by the AI for a project, with its own working state:
 * background motif and cell-by-cell edits.
 */
class FragmentVersion extends Model
{
    /** @use HasFactory<FragmentVersionFactory> */
    use HasFactory;

    public const KIND_GENERATION = 'generation';

    public const KIND_REFINEMENT = 'refinement';

    protected $fillable = [
        'project_id',
        'ai_run_id',
        'parent_id',
        'number',
        'kind',
        'style',
        'grid',
        'background_id',
        'score',
        'problems',
        'is_preferred',
        'raw_image_path',
        'edits',
        'motif',
    ];

    protected $attributes = [
        'problems' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'grid' => 'array',
            'score' => 'float',
            'problems' => 'array',
            'is_preferred' => 'boolean',
            'edits' => 'array',
            'motif' => 'array',
        ];
    }

    /** @return BelongsTo<FragmentProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(FragmentProject::class, 'project_id');
    }

    /** @return BelongsTo<FragmentAiRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(FragmentAiRun::class, 'ai_run_id');
    }

    /** @return BelongsTo<FragmentVersion, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Grid as exported: version → motif → edits. Edits outside the grid or with an
     * essence the palette no longer has are ignored.
     */
    public function finalGrid(ToolPalette $palette, MotifPainter $painter): Grid
    {
        $grid = new Grid($this->grid);

        $motif = $this->motif;
        if (is_array($motif) && ($motif['motif'] ?? 'none') !== 'none') {
            try {
                $grid = $painter->apply($grid, (string) $motif['motif'], (string) ($motif['density'] ?? ''), (int) ($motif['essence_id'] ?? 0), $this->background_id);
            } catch (InvalidArgumentException) {
                // Motif no longer valid: the version is shown without it.
            }
        }

        foreach ($this->edits ?? [] as [$row, $col, $id]) {
            if ($grid->inBounds($row, $col) && $palette->has($id)) {
                $grid = $grid->with($row, $col, $id);
            }
        }

        return $grid;
    }

    /**
     * Data the tool page needs to show the version.
     *
     * @return array{id: int, number: int, kind: string, style: ?string, parentNumber: ?int, grid: list<list<int>>, backgroundId: int, score: ?float, problems: list<string>, preferred: bool, edits: list<array{0: int, 1: int, 2: int}>, motif: ?array<string, mixed>}
     */
    public function toToolArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'kind' => $this->kind,
            'style' => $this->style,
            'parentNumber' => $this->parent?->number,
            'grid' => $this->grid,
            'backgroundId' => $this->background_id,
            'score' => $this->score,
            'problems' => $this->problems ?? [],
            'preferred' => $this->is_preferred,
            'edits' => $this->edits ?? [],
            'motif' => $this->motif,
        ];
    }
}
