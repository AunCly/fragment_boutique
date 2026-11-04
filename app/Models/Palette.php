<?php

namespace App\Models;

use Database\Factories\PaletteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Palette extends Model
{
    /** @use HasFactory<PaletteFactory> */
    use HasFactory;

    /** Minimum number of essences a palette needs to be usable by the Fragment tool. */
    public const MIN_ESSENCES = 2;

    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<WoodTexture, $this> */
    public function woodTextures(): BelongsToMany
    {
        return $this->belongsToMany(WoodTexture::class)
            ->orderBy('wood_textures.sort_order')
            ->orderBy('wood_textures.id');
    }

    /** @return HasMany<FragmentProject, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(FragmentProject::class);
    }

    /**
     * A palette used by a saved project can only be archived, and keeps its essences.
     */
    public function isUsedByProjects(): bool
    {
        return $this->projects()->exists();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
