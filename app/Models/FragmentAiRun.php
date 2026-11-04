<?php

namespace App\Models;

use Database\Factories\FragmentAiRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One paid AI call of a project (generation batch, refinement or suggestions),
 * recorded even when it fails.
 */
class FragmentAiRun extends Model
{
    /** @use HasFactory<FragmentAiRunFactory> */
    use HasFactory;

    public const KIND_GENERATE = 'generate';

    public const KIND_REFINE = 'refine';

    public const KIND_SUGGEST = 'suggest';

    /** Started and not finished: still running, or the process died. */
    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    /** Finished with some images missing. */
    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'project_id',
        'user_id',
        'version_id',
        'kind',
        'status',
        'parameters',
        'suggestions',
        'warnings',
        'error',
        'usage',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'suggestions' => 'array',
            'warnings' => 'array',
            'usage' => 'array',
            'cost_usd' => 'float',
        ];
    }

    /** @return BelongsTo<FragmentProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(FragmentProject::class, 'project_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Version the call worked on (refinement, suggestions).
     *
     * @return BelongsTo<FragmentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FragmentVersion::class, 'version_id');
    }

    /** @return HasMany<FragmentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(FragmentVersion::class, 'ai_run_id');
    }
}
