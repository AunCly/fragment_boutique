<?php

namespace App\Services\FragmentTool\Ai;

use App\Services\FragmentTool\ConversionPipeline;
use App\Services\FragmentTool\CropRect;
use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\Rendering\FlatRenderer;
use App\Services\FragmentTool\SourceImage;
use App\Services\FragmentTool\ToolPalette;
use GuzzleHttp\Promise\Utils;
use Throwable;

/**
 * AI redraw steps: generation, projection, judge review, refinement and suggestions.
 * The user is the judge: nothing is chosen or chained automatically.
 */
class AiPipeline
{
    /** Long side of the grids rendered for the models. */
    private const PREVIEW_LONG_SIDE = 512;

    /** @var list<GeminiUsage> */
    private array $usages = [];

    public function __construct(
        private readonly GeminiClient $client,
        private readonly AiProjector $projector,
        private readonly ConversionPipeline $conversion,
        private readonly FlatRenderer $flat,
    ) {}

    public function isEnabled(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Generates $count images in parallel, projects each one as soon as it arrives,
     * then asks the judge for its review.
     *
     * Candidate events carry the model image bytes in `raw_image` (never sent to the browser).
     *
     * @param  string  $imageModel  one of fragment_tool.ai.image_models
     * @param  bool  $outline  dark outline and light halo around the subject: asked in the prompts and imposed by the projection
     * @param  callable(array<string, mixed>): void  $emit  receives stage, warning, candidate, result and error events
     */
    public function generate(
        SourceImage $source,
        CropRect $crop,
        int $cols,
        int $rows,
        ToolPalette $palette,
        int $backgroundId,
        int $count,
        ?string $hint,
        string $imageModel,
        bool $outline,
        callable $emit,
    ): void {
        $this->usages = [];
        $haloId = $palette->haloId();
        $framingHaloId = $outline ? $haloId : null;
        $emit(['type' => 'stage', 'message' => 'Préparation de l\'image…']);
        $original = $this->originalImage($source, $crop, $palette, $backgroundId);

        $emit(['type' => 'stage', 'message' => $count > 1 ? "Génération de {$count} images…" : 'Génération de l\'image…']);

        $candidates = [];
        $promises = [];
        foreach (AiPrompts::stylesFor($count) as $index => $style) {
            $prompt = AiPrompts::generation($cols, $rows, $palette, $backgroundId, $haloId, $style, $hint, $outline);
            $promises[] = $this->client
                ->generateImageAsync($prompt, [$original], AiPrompts::aspectRatio($cols, $rows), $imageModel)
                ->then(function (mixed $result) use ($index, $style, $cols, $rows, $palette, $backgroundId, $framingHaloId, $emit, &$candidates) {
                    try {
                        $response = GeminiClient::unwrap($result);
                        $this->usages[] = $response['usage'];
                        $grid = $this->projector->project($response['image'], $cols, $rows, $palette, $backgroundId, $framingHaloId);
                    } catch (Throwable $e) {
                        $emit(['type' => 'warning', 'message' => 'Image '.($index + 1).' : '.$e->getMessage()]);

                        return;
                    }

                    $candidates[$index] = ['index' => $index, 'style' => $style, 'grid' => $grid];
                    $emit(['type' => 'candidate', 'index' => $index, 'style' => $style, 'grid' => $grid->toArray(), 'raw_image' => $response['image']]);
                });
        }

        Utils::settle($promises)->wait();
        ksort($candidates);

        if ($candidates === []) {
            $emit(['type' => 'error', 'message' => 'Aucune image n\'a pu être générée.', 'usage' => $this->usageSummary()]);

            return;
        }

        $emit(['type' => 'stage', 'message' => 'Avis du juge…']);
        $review = $this->reviewOrWarn($original, array_column($candidates, 'grid', 'index'), $palette, $backgroundId, $haloId, $outline, $emit);

        $emit([
            'type' => 'result',
            'candidates' => array_values(array_map(fn (array $candidate) => [
                'index' => $candidate['index'],
                'style' => $candidate['style'],
                'grid' => $candidate['grid']->toArray(),
                'score' => $review['scores'][$candidate['index']]['score'] ?? null,
                'problems' => $review['scores'][$candidate['index']]['problems'] ?? [],
            ], $candidates)),
            'preferred' => $review['preferred'],
            'usage' => $this->usageSummary(),
        ]);
    }

    /**
     * Sends the chosen version back to the image model with the user's remarks, then
     * projects and reviews the result. An unchanged grid is reported as such; a changed
     * one carries the model image bytes in `raw_image` (never sent to the browser).
     *
     * @param  list<string>  $remarks
     * @param  string  $imageModel  one of fragment_tool.ai.image_models
     * @param  bool  $outline  dark outline and light halo around the subject: asked in the prompts and imposed by the projection
     * @param  callable(array<string, mixed>): void  $emit
     */
    public function refine(
        SourceImage $source,
        CropRect $crop,
        int $cols,
        int $rows,
        ToolPalette $palette,
        int $backgroundId,
        Grid $grid,
        array $remarks,
        ?string $hint,
        string $imageModel,
        bool $outline,
        callable $emit,
    ): void {
        $this->usages = [];
        $haloId = $palette->haloId();
        $original = $this->originalImage($source, $crop, $palette, $backgroundId);

        try {
            $emit(['type' => 'stage', 'message' => 'Raffinement…']);
            $response = $this->client->generateImage(
                AiPrompts::refine($cols, $rows, $palette, $backgroundId, $haloId, $remarks, $hint, $outline),
                [$original, $this->gridImage($grid, $palette)],
                AiPrompts::aspectRatio($cols, $rows),
                $imageModel,
            );
            $this->usages[] = $response['usage'];
            $refined = $this->projector->project($response['image'], $cols, $rows, $palette, $backgroundId, $outline ? $haloId : null);
        } catch (Throwable $e) {
            $emit(['type' => 'error', 'message' => $e->getMessage(), 'usage' => $this->usageSummary()]);

            return;
        }

        if ($refined->equals($grid)) {
            $emit(['type' => 'result', 'unchanged' => true, 'grid' => $grid->toArray(), 'score' => null, 'problems' => [], 'usage' => $this->usageSummary()]);

            return;
        }

        $emit(['type' => 'stage', 'message' => 'Avis du juge…']);
        $review = $this->reviewOrWarn($original, [0 => $refined], $palette, $backgroundId, $haloId, $outline, $emit);

        $emit([
            'type' => 'result',
            'unchanged' => false,
            'grid' => $refined->toArray(),
            'raw_image' => $response['image'],
            'score' => $review['scores'][0]['score'] ?? null,
            'problems' => $review['scores'][0]['problems'] ?? [],
            'usage' => $this->usageSummary(),
        ]);
    }

    /**
     * Cell-by-cell corrections on the current grid (edits and motifs included), validated
     * and never applied automatically.
     *
     * @return array{suggestions: list<array{row: int, col: int, essence_id: int, reason: string}>, usage: array<string, mixed>}
     */
    public function suggest(SourceImage $source, CropRect $crop, ToolPalette $palette, int $backgroundId, Grid $grid): array
    {
        $this->usages = [];
        $codes = array_map(fn (array $row) => array_map(fn (int $id) => $palette->get($id)->code, $row), $grid->cells);

        $answer = $this->client->json(
            config('fragment_tool.ai.suggest_model'),
            AiPrompts::suggest($codes, $palette),
            [$this->originalImage($source, $crop, $palette, $backgroundId), $this->gridImage($grid, $palette)],
            AiPrompts::suggestSchema(),
        );
        $this->usages[] = $answer['usage'];

        return [
            'suggestions' => $this->validSuggestions($answer['data']['suggestions'] ?? [], $palette, $grid),
            'usage' => $this->usageSummary(),
        ];
    }

    /**
     * @param  array<int, Grid>  $grids  by candidate index
     * @return array{scores: array<int, array{score: ?float, problems: list<string>}>, preferred: ?int}
     */
    public function review(array $original, array $grids, ToolPalette $palette, int $backgroundId, int $haloId, bool $outline = true): array
    {
        $images = [$original];
        foreach ($grids as $grid) {
            $images[] = $this->gridImage($grid, $palette);
        }

        $answer = $this->client->json(
            config('fragment_tool.ai.judge_model'),
            AiPrompts::judge(count($grids), $backgroundId, $haloId, $palette, $outline),
            $images,
            AiPrompts::judgeSchema(),
        );
        $this->usages[] = $answer['usage'];

        // The judge numbers the candidates in the order they were sent.
        $indexes = array_keys($grids);
        $scores = [];
        foreach ((array) ($answer['data']['candidates'] ?? []) as $entry) {
            $position = is_array($entry) && is_numeric($entry['index'] ?? null) ? (int) $entry['index'] : null;
            if ($position === null || ! isset($indexes[$position])) {
                continue;
            }
            $scores[$indexes[$position]] = [
                'score' => JsonAnswer::score($entry['score'] ?? null),
                'problems' => array_values(array_slice(array_filter(array_map(
                    fn (mixed $problem) => is_string($problem) ? trim($problem) : '',
                    (array) ($entry['problems'] ?? []),
                )), 0, config('fragment_tool.ai.max_problems'))),
            ];
        }

        $preferred = $answer['data']['preferred'] ?? null;
        $preferred = is_numeric($preferred) && isset($indexes[(int) $preferred]) ? $indexes[(int) $preferred] : null;

        if ($preferred === null && $scores !== []) {
            $values = array_map(fn (array $entry) => $entry['score'] ?? -1.0, $scores);
            $preferred = array_keys($values, max($values))[0];
        }

        return ['scores' => $scores, 'preferred' => $preferred];
    }

    /**
     * @param  array{mime_type: string, data: string}  $original
     * @param  array<int, Grid>  $grids
     * @param  callable(array<string, mixed>): void  $emit
     * @return array{scores: array<int, array{score: ?float, problems: list<string>}>, preferred: ?int}
     */
    private function reviewOrWarn(array $original, array $grids, ToolPalette $palette, int $backgroundId, int $haloId, bool $outline, callable $emit): array
    {
        try {
            return $this->review($original, $grids, $palette, $backgroundId, $haloId, $outline);
        } catch (Throwable $e) {
            $emit(['type' => 'warning', 'message' => 'Juge indisponible, versions ajoutées sans note. '.$e->getMessage()]);

            return ['scores' => [], 'preferred' => null];
        }
    }

    /**
     * @param  mixed  $raw  suggestions as answered by the model (rows and columns from 1)
     * @return list<array{row: int, col: int, essence_id: int, reason: string}>
     */
    private function validSuggestions(mixed $raw, ToolPalette $palette, Grid $grid): array
    {
        $byCode = [];
        foreach ($palette->all() as $essence) {
            $byCode[mb_strtoupper($essence->code)] = $essence->id;
        }

        $valid = [];
        foreach ((array) $raw as $suggestion) {
            if (! is_array($suggestion) || ! is_numeric($suggestion['row'] ?? null) || ! is_numeric($suggestion['col'] ?? null)) {
                continue;
            }
            $row = (int) $suggestion['row'] - 1;
            $col = (int) $suggestion['col'] - 1;
            $id = $byCode[mb_strtoupper(trim((string) ($suggestion['code'] ?? '')))] ?? null;

            if ($id === null || ! $grid->inBounds($row, $col) || $grid->get($row, $col) === $id || isset($valid["{$row}:{$col}"])) {
                continue;
            }

            $valid["{$row}:{$col}"] = [
                'row' => $row,
                'col' => $col,
                'essence_id' => $id,
                'reason' => mb_substr(trim((string) ($suggestion['reason'] ?? '')), 0, 200),
            ];
        }

        return array_slice(array_values($valid), 0, config('fragment_tool.ai.max_suggestions'));
    }

    /**
     * @return array{mime_type: string, data: string}
     */
    private function originalImage(SourceImage $source, CropRect $crop, ToolPalette $palette, int $backgroundId): array
    {
        $flat = $this->conversion->flattenedCrop($source, $crop, $palette, $backgroundId);

        return ['mime_type' => 'image/jpeg', 'data' => SourceImage::toJpeg($flat)];
    }

    /**
     * @return array{mime_type: string, data: string}
     */
    private function gridImage(Grid $grid, ToolPalette $palette): array
    {
        $cell = max(4, intdiv(self::PREVIEW_LONG_SIDE, max($grid->cols, $grid->rows)));

        return ['mime_type' => 'image/png', 'data' => $this->flat->render($grid, $palette, $cell)];
    }

    /**
     * @return array{cost_usd: float, calls: list<array<string, mixed>>}
     */
    private function usageSummary(): array
    {
        return [
            'cost_usd' => round(array_sum(array_map(fn (GeminiUsage $usage) => $usage->costUsd, $this->usages)), 5),
            'calls' => array_map(fn (GeminiUsage $usage) => $usage->toArray(), $this->usages),
        ];
    }
}
