<?php

namespace App\Models;

use Database\Factories\GalleryImageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GalleryImage extends Model
{
    /** @use HasFactory<GalleryImageFactory> */
    use HasFactory;

    public const DEFAULT_ALT = 'Un Fragment assemblé, installé dans un intérieur';

    protected $fillable = [
        'path',
        'alt',
        'sort_order',
    ];

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function getAltTextAttribute(): string
    {
        return $this->alt ?: self::DEFAULT_ALT;
    }
}
