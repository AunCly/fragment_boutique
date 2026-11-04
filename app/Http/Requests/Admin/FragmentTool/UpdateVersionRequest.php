<?php

namespace App\Http\Requests\Admin\FragmentTool;

use App\Models\FragmentProject;
use App\Models\FragmentVersion;
use App\Services\FragmentTool\MotifPainter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Autosave of a version working state: cell-by-cell edits and background motif.
 */
class UpdateVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var FragmentProject $project */
        $project = $this->route('project');
        /** @var FragmentVersion $version */
        $version = $this->route('version');
        $palette = $project->toolPalette();
        $rows = count($version->grid);
        $cols = count($version->grid[0] ?? []);

        $inPalette = function (string $attribute, mixed $value, Closure $fail) use ($palette): void {
            if (! is_int($value) || ! $palette->has($value)) {
                $fail('L\'essence doit appartenir à la palette.');
            }
        };

        return [
            'edits' => ['sometimes', 'array', 'max:'.($rows * $cols)],
            'edits.*' => ['array', 'size:3', 'list'],
            'edits.*.0' => ['required', 'integer', 'between:0,'.($rows - 1)],
            'edits.*.1' => ['required', 'integer', 'between:0,'.($cols - 1)],
            'edits.*.2' => ['required', 'integer', $inPalette],
            'motif' => ['sometimes', 'array'],
            'motif.motif' => ['required_with:motif', 'string', Rule::in(array_keys(MotifPainter::MOTIFS))],
            'motif.density' => ['required_with:motif', 'string', Rule::in(array_keys(MotifPainter::DENSITIES))],
            'motif.essence_id' => ['required_with:motif', 'integer', $inPalette],
        ];
    }
}
