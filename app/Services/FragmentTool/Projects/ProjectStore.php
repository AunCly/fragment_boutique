<?php

namespace App\Services\FragmentTool\Projects;

use App\Models\Format;
use App\Models\FragmentProject;
use App\Services\FragmentTool\CropRect;
use App\Services\FragmentTool\SourceImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Creates saved projects and stores their files on the private disk.
 */
class ProjectStore
{
    /**
     * New project from the uploaded image, with a list thumbnail of the source.
     */
    public function create(
        UploadedFile $upload,
        SourceImage $source,
        CropRect $crop,
        Format $format,
        int $paletteId,
        int $backgroundId,
        ?string $hint,
        ?int $userId,
    ): FragmentProject {
        return DB::transaction(function () use ($upload, $source, $crop, $format, $paletteId, $backgroundId, $hint, $userId) {
            $project = FragmentProject::create([
                'name' => mb_substr($source->name, 0, 120),
                'created_by' => $userId,
                'source_path' => '',
                'thumbnail_path' => '',
                'source_width' => $source->width(),
                'source_height' => $source->height(),
                'crop_x' => $crop->x,
                'crop_y' => $crop->y,
                'crop_width' => $crop->width,
                'crop_height' => $crop->height,
                'format_id' => $format->id,
                'palette_id' => $paletteId,
                'background_id' => $backgroundId,
                'hint' => $hint,
            ]);

            $disk = Storage::disk(FragmentProject::DISK);
            $extension = str_starts_with((string) file_get_contents($upload->getRealPath(), length: 4), "\x89PNG") ? 'png' : 'jpg';
            $sourcePath = "{$project->directory()}/source.{$extension}";
            $thumbnailPath = "{$project->directory()}/thumbnail.jpg";

            $disk->put($sourcePath, (string) file_get_contents($upload->getRealPath()));
            $disk->put($thumbnailPath, $this->thumbnail($source));

            $project->update(['source_path' => $sourcePath, 'thumbnail_path' => $thumbnailPath]);

            return $project;
        });
    }

    /**
     * Stores the image returned by the model for one version.
     */
    public function storeRawImage(FragmentProject $project, int $versionNumber, string $jpeg): string
    {
        $path = "{$project->directory()}/raw/{$versionNumber}.jpg";
        Storage::disk(FragmentProject::DISK)->put($path, $jpeg);

        return $path;
    }

    private function thumbnail(SourceImage $source): string
    {
        $whole = new CropRect(0, 0, $source->width(), $source->height());

        return SourceImage::toJpeg($source->crop($whole, FragmentProject::THUMBNAIL_LONG_SIDE), quality: 85);
    }
}
