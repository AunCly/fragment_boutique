<?php

namespace App\Http\Requests\Admin\FragmentTool;

use App\Models\FragmentProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Autosave of a project: name and chosen version.
 */
class UpdateProjectRequest extends FormRequest
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

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'selected_version_id' => ['sometimes', 'nullable', 'integer', Rule::exists('fragment_versions', 'id')->where('project_id', $project->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'selected_version_id.exists' => 'Cette version n\'appartient pas au projet.',
        ];
    }
}
