<?php

namespace App\Services\FragmentTool\Projects;

use App\Models\FragmentAiRun;
use App\Models\FragmentProject;
use App\Models\FragmentVersion;

/**
 * Saves a streamed AI call as it happens: each version as soon as it arrives, then
 * scores, status and cost. Wraps the event emitter so the browser receives the saved
 * versions (with their ids) and never the raw model images.
 */
class AiRunRecorder
{
    /** @var array<int, FragmentVersion> by candidate index */
    private array $versions = [];

    private function __construct(
        private readonly ProjectStore $store,
        private readonly FragmentProject $project,
        public readonly FragmentAiRun $run,
        private readonly int $backgroundId,
        private readonly ?FragmentVersion $parent,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function start(
        ProjectStore $store,
        FragmentProject $project,
        string $kind,
        array $parameters,
        int $backgroundId,
        ?FragmentVersion $version,
        ?int $userId,
    ): self {
        $run = $project->runs()->create([
            'user_id' => $userId,
            'version_id' => $version?->id,
            'kind' => $kind,
            'status' => FragmentAiRun::STATUS_RUNNING,
            'parameters' => $parameters,
        ]);

        return new self($store, $project, $run, $backgroundId, $version);
    }

    /**
     * @param  callable(array<string, mixed>): void  $emit
     * @return callable(array<string, mixed>): void
     */
    public function wrap(callable $emit): callable
    {
        return function (array $event) use ($emit): void {
            $emit($this->record($event));
        };
    }

    /**
     * Marks the run as failed when the pipeline stopped without a result or an error.
     */
    public function failIfUnfinished(string $message = 'Appel interrompu.'): void
    {
        if ($this->run->status === FragmentAiRun::STATUS_RUNNING) {
            $this->run->update(['status' => FragmentAiRun::STATUS_FAILED, 'error' => $message]);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function record(array $event): array
    {
        return match ($event['type'] ?? null) {
            'candidate' => $this->candidate($event),
            'warning' => $this->warning($event),
            'result' => $this->run->kind === FragmentAiRun::KIND_REFINE ? $this->refined($event) : $this->generated($event),
            'error' => $this->error($event),
            default => $event,
        };
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function candidate(array $event): array
    {
        $version = $this->createVersion($event['grid'], $event['style'] ?? null, $event['raw_image'] ?? null);
        $this->versions[(int) $event['index']] = $version;

        unset($event['raw_image']);

        return [...$event, 'version' => $version->toToolArray()];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function warning(array $event): array
    {
        $this->run->update(['warnings' => [...($this->run->warnings ?? []), (string) $event['message']]]);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function generated(array $event): array
    {
        $saved = [];
        foreach ($event['candidates'] as $candidate) {
            $version = $this->versions[$candidate['index']] ?? $this->createVersion($candidate['grid'], $candidate['style'] ?? null, null);
            $version->update([
                'score' => $candidate['score'],
                'problems' => $candidate['problems'],
                'is_preferred' => $candidate['index'] === $event['preferred'],
            ]);
            $saved[] = $version->toToolArray();
        }

        $complete = count($saved) >= (int) ($this->run->parameters['count'] ?? 0);
        $this->finish($complete ? FragmentAiRun::STATUS_SUCCEEDED : FragmentAiRun::STATUS_PARTIAL, $event['usage'] ?? null);

        return [...$event, 'versions' => $saved];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function refined(array $event): array
    {
        $rawImage = $event['raw_image'] ?? null;
        unset($event['raw_image']);

        if (! ($event['unchanged'] ?? false)) {
            $version = $this->createVersion($event['grid'], null, $rawImage, [
                'score' => $event['score'],
                'problems' => $event['problems'],
            ]);
            $event['version'] = $version->toToolArray();
        }

        $this->finish(FragmentAiRun::STATUS_SUCCEEDED, $event['usage'] ?? null);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function error(array $event): array
    {
        $this->finish(
            $this->versions === [] ? FragmentAiRun::STATUS_FAILED : FragmentAiRun::STATUS_PARTIAL,
            $event['usage'] ?? null,
            (string) $event['message'],
        );

        return $event;
    }

    /**
     * @param  list<list<int>>  $grid
     * @param  array<string, mixed>  $attributes
     */
    private function createVersion(array $grid, ?string $style, ?string $rawImage, array $attributes = []): FragmentVersion
    {
        $number = $this->project->nextVersionNumber();
        $refinement = $this->run->kind === FragmentAiRun::KIND_REFINE;

        $version = $this->project->versions()->create([
            'ai_run_id' => $this->run->id,
            'parent_id' => $refinement ? $this->parent?->id : null,
            'number' => $number,
            'kind' => $refinement ? FragmentVersion::KIND_REFINEMENT : FragmentVersion::KIND_GENERATION,
            'style' => $style,
            'grid' => $grid,
            'background_id' => $this->backgroundId,
            'raw_image_path' => $rawImage !== null ? $this->store->storeRawImage($this->project, $number, $rawImage) : null,
            ...$attributes,
        ]);

        return $version->setRelation('parent', $refinement ? $this->parent : null);
    }

    /**
     * @param  array{cost_usd?: float, calls?: list<array<string, mixed>>}|null  $usage
     */
    private function finish(string $status, ?array $usage, ?string $error = null): void
    {
        $cost = (float) ($usage['cost_usd'] ?? 0);

        $this->run->update([
            'status' => $status,
            'usage' => $usage['calls'] ?? null,
            'cost_usd' => $cost,
            'error' => $error,
        ]);
        $this->project->addCost($cost);
    }
}
