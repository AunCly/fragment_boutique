<?php

namespace App\Models;

use Database\Factories\WoodTextureFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class WoodTexture extends Model
{
    /** @use HasFactory<WoodTextureFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'hex',
        'texture_path',
        'tags',
        'grid_code',
        'sort_order',
        'is_active',
    ];

    protected $attributes = [
        'tags' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Palette, $this> */
    public function palettes(): BelongsToMany
    {
        return $this->belongsToMany(Palette::class);
    }

    /**
     * An essence of a palette used by a saved project (or a project background) can only be archived.
     */
    public function isUsedByProjects(): bool
    {
        return in_array($this->id, self::idsUsedByProjects(), true);
    }

    /**
     * @return list<int>
     */
    public static function idsUsedByProjects(): array
    {
        $paletteIds = FragmentProject::query()->select('palette_id');

        return self::query()
            ->whereHas('palettes', fn (Builder $query) => $query->whereIn('palettes.id', $paletteIds))
            ->orWhereIn('id', FragmentProject::query()->select('background_id'))
            ->pluck('id')
            ->all();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function getTextureUrlAttribute(): ?string
    {
        return $this->texture_path ? Storage::url($this->texture_path) : null;
    }
}
