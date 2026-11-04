<?php

namespace Tests\Feature\FragmentTool\Concerns;

use App\Services\FragmentTool\SourceImage;
use Illuminate\Http\Client\Request;
use Illuminate\Testing\TestResponse;

/**
 * Gemini answers for Http::fake() and reading of the NDJSON streams.
 */
trait FakesGemini
{
    /**
     * 1024 × 1024 JPEG drawn like the model does: 16 × 16 big pixels.
     */
    protected function modelImage(string $subjectHex = '#c26d2d', string $backgroundHex = '#9fb4c8'): string
    {
        $png = $this->pixelArtPng($this->subjectHexGrid(16, 16, $backgroundHex, $subjectHex, margin: 5), 64);

        return SourceImage::toJpeg(imagecreatefromstring($png));
    }

    /**
     * @return array<string, mixed>
     */
    protected function imageInteraction(?string $jpeg = null): array
    {
        return [
            'id' => 'interaction-1',
            'steps' => [
                ['type' => 'thought', 'summary' => 'Planning the pixel art…'],
                ['type' => 'model_output', 'content' => [
                    ['type' => 'image', 'mime_type' => 'image/jpeg', 'data' => base64_encode($jpeg ?? $this->modelImage())],
                ]],
            ],
            'usage' => ['total_input_tokens' => 1500, 'total_output_tokens' => 1290, 'total_thought_tokens' => 200],
        ];
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array<string, mixed>
     */
    protected function textInteraction(array $answer, bool $fenced = true): array
    {
        $json = json_encode($answer);

        return [
            'steps' => [['type' => 'model_output', 'content' => [
                ['type' => 'text', 'text' => $fenced ? "Voici mon avis :\n```json\n{$json}\n```" : $json],
            ]]],
            'usage' => ['total_input_tokens' => 3000, 'total_output_tokens' => 150],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function events(TestResponse $response): array
    {
        $response->assertOk();
        $this->assertStringStartsWith('application/x-ndjson', $response->headers->get('Content-Type'));

        return array_map(
            fn (string $line) => json_decode($line, true),
            array_values(array_filter(explode("\n", $response->streamedContent()))),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    protected function ofType(array $events, string $type): array
    {
        return array_values(array_filter($events, fn (array $event) => $event['type'] === $type));
    }

    protected function isImageRequest(Request $request): bool
    {
        return ($request->data()['response_format']['type'] ?? null) === 'image';
    }
}
