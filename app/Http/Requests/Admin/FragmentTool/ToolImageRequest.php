<?php

namespace App\Http\Requests\Admin\FragmentTool;

use App\Services\FragmentTool\CropRect;
use App\Services\FragmentTool\InvalidImageException;
use App\Services\FragmentTool\SourceImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Tool call working on a source image and its crop: uploaded with the request, or read
 * from a saved project.
 */
class ToolImageRequest extends ToolRequest
{
    private ?SourceImage $sourceImage = null;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->hasProject()) {
            return parent::rules();
        }

        return [
            ...parent::rules(),
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:'.config('fragment_tool.max_upload_kb')],
            'crop_x' => ['required', 'integer', 'min:0'],
            'crop_y' => ['required', 'integer', 'min:0'],
            'crop_width' => ['required', 'integer', 'min:1'],
            'crop_height' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'image.mimes' => 'Format non supporté : importez une image PNG ou JPG.',
            'image.max' => 'Image trop lourde.',
        ];
    }

    public function source(): SourceImage
    {
        try {
            return $this->sourceImage ??= $this->project()?->source() ?? SourceImage::fromUpload($this->file('image'));
        } catch (InvalidImageException $e) {
            throw ValidationException::withMessages(['image' => $e->getMessage()]);
        }
    }

    public function crop(): CropRect
    {
        if ($project = $this->project()) {
            return $project->crop();
        }

        $crop = new CropRect(
            $this->integer('crop_x'),
            $this->integer('crop_y'),
            $this->integer('crop_width'),
            $this->integer('crop_height'),
        );
        $format = $this->toolFormat();

        try {
            $crop->validateFor($this->source()->width(), $this->source()->height(), $format->cols, $format->rows);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['crop' => $e->getMessage()]);
        }

        return $crop;
    }
}
