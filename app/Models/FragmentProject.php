<?php

namespace App\Models;

use App\Services\FragmentTool\CropRect;
use App\Services\FragmentTool\SourceImage;
use App\Services\FragmentTool\ToolPalette;
use Database\Factories\FragmentProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A saved Fragment tool project: one source image, crop, format and palette, fixed
 * once the first version exists. Shared by every admin.
 */
class FragmentProject extends Model
{
    /** @use HasFactory<FragmentProjectFactory> */
    use HasFactory;

    /** Disk holding source images, thumbnails and raw model images (private). */
    public const DISK = 'local';

    /** Long side of the source thumbnail shown in the project list. */
    public const THUMBNAIL_LONG_SIDE = 320;

    protected $fillable = [
        'name',
        'created_by',
        'source_path',
        'thumbnail_path',
        'source_width',
        'source_height',
        'crop_x',
        'crop_y',
        'crop_width',
        'crop_height',
        'format_id',
        'palette_id',
        'background_id',
        'hint',
        'selected_version_id',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'source_width' => 'integer',
            'source_height' => 'integer',
            'crop_x' => 'integer',
            'crop_y' => 'integer',
            'crop_width' => 'integer',
            'crop_height' => 'integer',
            'cost_usd' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (FragmentProject $project) {
            Storage::disk(self::DISK)->deleteDirectory($project->directory());
        });
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Format, $this> */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }

    /** @return BelongsTo<Palette, $this> */
    public function palette(): BelongsTo
    {
        return $this->belongsTo(Palette::class);
    }

    /** @return BelongsTo<WoodTexture, $this> */
    public function background(): BelongsTo
    {
        return $this->belongsTo(WoodTexture::class, 'background_id');
    }

    /** @return HasMany<FragmentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(FragmentVersion::class, 'project_id')->orderBy('number');
    }

    /** @return HasMany<FragmentAiRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(FragmentAiRun::class, 'project_id')->orderBy('id');
    }

    /** @return BelongsTo<FragmentVersion, $this> */
    public function selectedVersion(): BelongsTo
    {
        return $this->belongsTo(FragmentVersion::class, 'selected_version_id');
    }

    /**
     * Storage folder of the project files.
     */
    public function directory(): string
    {
        return "fragment-tool/projects/{$this->id}";
    }

    public function crop(): CropRect
    {
        return new CropRect($this->crop_x, $this->crop_y, $this->crop_width, $this->crop_height);
    }

    public function source(): SourceImage
    {
        return SourceImage::fromBytes(
            (string) Storage::disk(self::DISK)->get($this->source_path),
            pathinfo($this->source_path, PATHINFO_FILENAME),
        );
    }

    /**
     * Palette as the tool uses it, archived or not.
     */
    public function toolPalette(): ToolPalette
    {
        return ToolPalette::fromPalette($this->palette->loadMissing('woodTextures'));
    }

    public function nextVersionNumber(): int
    {
        return (int) $this->versions()->max('number') + 1;
    }

    public function addCost(float $costUsd): void
    {
        if ($costUsd > 0) {
            $this->increment('cost_usd', $costUsd);
        }
    }
}
