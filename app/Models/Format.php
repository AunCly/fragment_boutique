<?php

namespace App\Models;

use Database\Factories\FormatFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Format extends Model
{
    /** @use HasFactory<FormatFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'label',
        'cols',
        'rows',
        'sort_order',
        'is_active',
        'is_tool_enabled',
    ];

    protected function casts(): array
    {
        return [
            'cols' => 'integer',
            'rows' => 'integer',
            'is_active' => 'boolean',
            'is_tool_enabled' => 'boolean',
        ];
    }

    /** @return HasMany<FragmentProject, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(FragmentProject::class);
    }

    /**
     * A format used by a saved project can only be archived.
     */
    public function isUsedByProjects(): bool
    {
        return $this->projects()->exists();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForTool(Builder $query): void
    {
        $query->where('is_tool_enabled', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function getShapeAttribute(): string
    {
        if ($this->cols === $this->rows) {
            return 'square';
        }

        return $this->cols > $this->rows ? 'landscape' : 'portrait';
    }
}
