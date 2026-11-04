<?php

namespace App\Http\Requests\Admin\FragmentTool;

use App\Models\Format;
use App\Models\FragmentProject;
use App\Models\Palette;
use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\ToolPalette;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Format, palette and background essence shared by every Fragment tool call.
 * With a saved project, its format and palette are used (even archived) instead.
 * Optional fields of the individual calls are validated here too, when present.
 */
class ToolRequest extends FormRequest
{
    private ?ToolPalette $toolPalette = null;

    private ?FragmentProject $fragmentProject = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->hasProject()) {
            return [
                'project_id' => ['required', 'integer', Rule::exists('fragment_projects', 'id')],
                'background_id' => ['nullable', 'integer'],
            ];
        }

        return [
            'format_id' => ['required', 'integer', Rule::exists('formats', 'id')->where('is_tool_enabled', true)],
            'palette_id' => ['required', 'integer', Rule::exists('palettes', 'id')->where('is_active', true)],
            'background_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format_id.exists' => 'Ce format n\'est pas disponible dans l\'outil.',
            'palette_id.exists' => 'Cette palette n\'est pas disponible dans l\'outil.',
        ];
    }

    public function hasProject(): bool
    {
        return $this->filled('project_id');
    }

    public function project(): ?FragmentProject
    {
        if (! $this->hasProject()) {
            return null;
        }

        return $this->fragmentProject ??= FragmentProject::with(['format', 'palette.woodTextures'])->findOrFail($this->integer('project_id'));
    }

    public function toolFormat(): Format
    {
        return $this->project()?->format ?? Format::findOrFail($this->integer('format_id'));
    }

    public function palette(): ToolPalette
    {
        if ($this->toolPalette !== null) {
            return $this->toolPalette;
        }

        try {
            return $this->toolPalette = $this->project()?->toolPalette()
                ?? ToolPalette::fromPalette(Palette::with('woodTextures')->findOrFail($this->integer('palette_id')));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['palette_id' => $e->getMessage()]);
        }
    }

    /**
     * Chosen background essence, or the project one, or the palette default (Hêtre when present).
     */
    public function backgroundId(): int
    {
        if (! $this->filled('background_id')) {
            return $this->project()?->background_id ?? $this->palette()->defaultBackgroundId();
        }

        $id = $this->integer('background_id');
        if (! $this->palette()->has($id)) {
            throw ValidationException::withMessages(['background_id' => 'L\'essence de fond doit appartenir à la palette.']);
        }

        return $id;
    }

    public function grid(string $key = 'grid'): Grid
    {
        $format = $this->toolFormat();

        try {
            return Grid::parse($this->input($key), $format->cols, $format->rows, $this->palette());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$key => $e->getMessage()]);
        }
    }
}
