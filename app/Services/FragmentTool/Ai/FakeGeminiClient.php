<?php

namespace App\Services\FragmentTool\Ai;

use App\Services\FragmentTool\SourceImage;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Random\Randomizer;

/**
 * Test mode: answers like Gemini without any network call, at no cost. The
 * "generated" image is the original crop with a filter, the judge and the
 * suggestions are random but valid.
 */
class FakeGeminiClient extends GeminiClient
{
    private const PROBLEMS = [
        'Contour du sujet interrompu.',
        'Œil peu lisible.',
        'Trop de morceaux isolés dans le fond.',
        'Contraste faible entre le sujet et le fond.',
    ];

    private int $calls = 0;

    private readonly Randomizer $random;

    public function __construct()
    {
        $this->random = new Randomizer;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @param  list<array{mime_type: string, data: string}>  $images
     */
    public function generateImageAsync(string $prompt, array $images, string $aspectRatio, ?string $model = null): PromiseInterface
    {
        $this->wait();
        $model ??= config('fragment_tool.ai.image_model');

        return Create::promiseFor([
            'image' => $this->filtered($images[0]['data'], $this->calls++),
            'text' => '',
            'usage' => new GeminiUsage($model, producesImage: true),
        ]);
    }

    /**
     * @param  list<array{mime_type: string, data: string}>  $images
     * @param  array<string, mixed>  $schema
     * @return array{data: array<string, mixed>, usage: GeminiUsage}
     */
    public function json(string $model, string $prompt, array $images, array $schema): array
    {
        $this->wait();

        $data = isset($schema['properties']['candidates'])
            ? $this->review(count($images) - 1)
            : ['suggestions' => $this->suggestions($prompt)];

        return ['data' => $data, 'usage' => new GeminiUsage($model)];
    }

    /**
     * @return array{candidates: list<array{index: int, score: int, problems: list<string>}>, preferred: int}
     */
    private function review(int $count): array
    {
        $candidates = [];
        for ($index = 0; $index < $count; $index++) {
            $problems = $this->random->shuffleArray(self::PROBLEMS);
            $candidates[] = [
                'index' => $index,
                'score' => $this->random->getInt(5, 9),
                'problems' => array_slice($problems, 0, $this->random->getInt(0, 2)),
            ];
        }

        $scores = array_column($candidates, 'score');

        return ['candidates' => $candidates, 'preferred' => array_keys($scores, max($scores))[0]];
    }

    /**
     * A few cell changes read from the grid and legend written in the prompt.
     *
     * @return list<array{row: int, col: int, code: string, reason: string}>
     */
    private function suggestions(string $prompt): array
    {
        preg_match_all('/^(\S+) = /m', $prompt, $legend);
        $codes = $legend[1];
        $grid = trim(preg_split('/^Grid:$/m', $prompt)[1] ?? '');
        $lines = array_slice(explode("\n", $grid), 0, -1);
        $rows = count($lines);
        $cols = $rows > 0 ? count(explode(' ', $lines[0])) : 0;

        if (count($codes) < 2 || $rows === 0 || $cols === 0) {
            return [];
        }

        $suggestions = [];
        for ($i = 0; $i < 5; $i++) {
            $suggestions[] = [
                'row' => $this->random->getInt(1, $rows),
                'col' => $this->random->getInt(1, $cols),
                'code' => $codes[$this->random->getInt(0, count($codes) - 1)],
                'reason' => 'Suggestion de test.',
            ];
        }

        return $suggestions;
    }

    /**
     * The original crop, with a different filter per call so versions differ.
     */
    private function filtered(string $jpeg, int $call): string
    {
        $image = imagecreatefromstring($jpeg);

        match ($call % 4) {
            0 => null,
            1 => imagefilter($image, IMG_FILTER_CONTRAST, -25),
            2 => imagefilter($image, IMG_FILTER_SMOOTH, 4),
            3 => imagefilter($image, IMG_FILTER_BRIGHTNESS, 20),
        };

        return SourceImage::toJpeg($image);
    }

    /**
     * Short pause so the progress popup and timer can be seen.
     */
    private function wait(): void
    {
        usleep(config('fragment_tool.ai.test_mode_delay_ms') * 1000);
    }
}
