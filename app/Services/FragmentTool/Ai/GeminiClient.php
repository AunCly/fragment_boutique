<?php

namespace App\Services\FragmentTool\Ai;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Calls to the Gemini Interactions API (POST /v1beta/interactions), `store: false`.
 * A temporary overload (503) is retried once on every call.
 */
class GeminiClient
{
    public function __construct(private readonly HttpFactory $http) {}

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Generates one image. Resolves to array{image: string (JPEG bytes), text: string, usage: GeminiUsage}
     * or to a GeminiException.
     *
     * @param  list<array{mime_type: string, data: string}>  $images  raw bytes in `data`
     */
    public function generateImageAsync(string $prompt, array $images, string $aspectRatio): PromiseInterface
    {
        $model = config('fragment_tool.ai.image_model');
        $payload = [
            'model' => $model,
            'input' => $this->input($prompt, $images),
            'store' => false,
            'response_format' => [
                'type' => 'image',
                'mime_type' => 'image/jpeg',
                'aspect_ratio' => $aspectRatio,
                'image_size' => '1K',
            ],
        ];

        return $this->send($payload)->then(function (mixed $json) use ($model) {
            if ($json instanceof Throwable) {
                return $json;
            }

            $image = self::findImage($json);
            if ($image === null) {
                return new GeminiException('Gemini n\'a renvoyé aucune image.');
            }

            $usage = GeminiUsage::fromResponse($model, $json, producesImage: true);
            $this->log('image', $usage);

            return ['image' => $image, 'text' => self::findText($json), 'usage' => $usage];
        }, fn (Throwable $e) => $e instanceof GeminiException ? $e : new GeminiException($e->getMessage()));
    }

    /**
     * @param  list<array{mime_type: string, data: string}>  $images
     * @return array{image: string, text: string, usage: GeminiUsage}
     */
    public function generateImage(string $prompt, array $images, string $aspectRatio): array
    {
        return self::unwrap($this->generateImageAsync($prompt, $images, $aspectRatio)->wait());
    }

    /**
     * Structured JSON answer from a text model.
     *
     * @param  list<array{mime_type: string, data: string}>  $images
     * @param  array<string, mixed>  $schema
     * @return array{data: array<string, mixed>, usage: GeminiUsage}
     */
    public function json(string $model, string $prompt, array $images, array $schema): array
    {
        $payload = [
            'model' => $model,
            'input' => $this->input($prompt, $images),
            'store' => false,
            'response_format' => [
                'type' => 'text',
                'mime_type' => 'application/json',
                'schema' => $schema,
            ],
        ];

        $json = self::unwrap($this->send($payload)->wait());
        $usage = GeminiUsage::fromResponse($model, $json, producesImage: false);
        $this->log('json', $usage);

        return ['data' => JsonAnswer::parse(self::findText($json)), 'usage' => $usage];
    }

    /**
     * Base64 data of the first image block of an interaction, decoded.
     *
     * @param  array<mixed>  $json
     */
    public static function findImage(array $json): ?string
    {
        if (isset($json['output_image']['data']) && is_string($json['output_image']['data'])) {
            return base64_decode($json['output_image']['data'], true) ?: null;
        }

        foreach (self::blocks($json) as $block) {
            if (($block['type'] ?? null) === 'image' && is_string($block['data'] ?? null)) {
                return base64_decode($block['data'], true) ?: null;
            }
        }

        return null;
    }

    /**
     * Text of the model output (thoughts excluded).
     *
     * @param  array<mixed>  $json
     */
    public static function findText(array $json): string
    {
        if (is_string($json['output_text'] ?? null)) {
            return $json['output_text'];
        }

        $texts = [];
        foreach (self::blocks($json) as $block) {
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $texts[] = $block['text'];
            }
        }

        return implode("\n", $texts);
    }

    /**
     * Content blocks of the response, walking steps / outputs and skipping thoughts.
     *
     * @param  array<mixed>  $node
     * @return list<array<string, mixed>>
     */
    private static function blocks(array $node): array
    {
        $blocks = [];

        foreach (['steps', 'outputs', 'content', 'parts'] as $key) {
            foreach ((array) ($node[$key] ?? []) as $child) {
                if (! is_array($child) || in_array($child['type'] ?? null, ['thought', 'user_input'], true)) {
                    continue;
                }
                if (in_array($child['type'] ?? null, ['text', 'image'], true)) {
                    $blocks[] = $child;
                }
                array_push($blocks, ...self::blocks($child));
            }
        }

        return $blocks;
    }

    /**
     * @param  list<array{mime_type: string, data: string}>  $images
     * @return list<array<string, string>>
     */
    private function input(string $prompt, array $images): array
    {
        $input = [['type' => 'text', 'text' => $prompt]];

        foreach ($images as $image) {
            $input[] = ['type' => 'image', 'mime_type' => $image['mime_type'], 'data' => base64_encode($image['data'])];
        }

        return $input;
    }

    /**
     * Resolves to the decoded JSON body, or to a GeminiException.
     *
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload, int $attempt = 0): PromiseInterface
    {
        return $this->http
            ->async()
            ->withHeaders([
                'x-goog-api-key' => (string) config('services.gemini.key'),
                'Api-Revision' => config('fragment_tool.ai.api_revision'),
            ])
            ->acceptJson()
            ->timeout(config('fragment_tool.ai.timeout'))
            ->post(config('fragment_tool.ai.endpoint'), $payload)
            ->then(function (mixed $response) use ($payload, $attempt) {
                if (! $response instanceof Response) {
                    return new GeminiException('Gemini injoignable : '.($response instanceof Throwable ? $response->getMessage() : 'erreur réseau'));
                }

                if ($response->status() === 503 && $attempt === 0) {
                    return $this->send($payload, 1);
                }

                if ($response->failed()) {
                    return GeminiException::fromStatus($response->status(), (string) $response->json('error.message', ''));
                }

                return $response->json() ?? new GeminiException('Réponse de Gemini illisible.');
            });
    }

    /**
     * @template T
     *
     * @param  T|GeminiException  $value
     * @return T
     */
    public static function unwrap(mixed $value): mixed
    {
        if ($value instanceof Throwable) {
            throw $value instanceof GeminiException ? $value : new GeminiException($value->getMessage());
        }

        return $value;
    }

    private function log(string $kind, GeminiUsage $usage): void
    {
        Log::info('Fragment tool Gemini call', ['kind' => $kind, ...$usage->toArray()]);
    }
}
