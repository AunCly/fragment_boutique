<?php

namespace App\Models;

use Database\Factories\FormatFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected function casts(): array
    {
        return [
            'cols' => 'integer',
            'rows' => 'integer',
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

    public function getShapeAttribute(): string
    {
        if ($this->cols === $this->rows) {
            return 'square';
        }

        return $this->cols > $this->rows ? 'landscape' : 'portrait';
    }

    /** Returns the entry formatted for the React builder (matches FormatOption interface). */
    public function toBuilderEntry(): array
    {
        return [
            'key' => $this->slug,
            'shape' => $this->shape,
            'label' => $this->label,
            'format' => ['cols' => $this->cols, 'rows' => $this->rows],
        ];
    }
}
