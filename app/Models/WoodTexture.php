<?php

namespace App\Models;

use Database\Factories\WoodTextureFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    /** Returns the entry formatted for the React builder (matches LibraryEntry interface). */
    public function toBuilderEntry(): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'hex' => $this->hex,
            'textureUrl' => $this->texture_url, // via accessor
            'tags' => $this->tags,
            'gridCode' => $this->grid_code,
            'fromCloud' => true,
        ];
    }
}
