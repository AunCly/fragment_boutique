<?php

namespace App\Services\FragmentTool\Ai;

/**
 * Tokens consumed by one call and its cost, computed from PRICES_PER_M.
 */
final readonly class GeminiUsage
{
    public float $costUsd;

    public function __construct(
        public string $model,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $thoughtTokens = 0,
        public bool $producesImage = false,
    ) {
        // Model names contain dots: no dot notation on them.
        $prices = config('fragment_tool.ai.prices_per_m')[$model] ?? ['input' => 0.0, 'output' => 0.0, 'image' => 0.0];
        $outputRate = $producesImage ? ($prices['image'] ?? 0.0) : ($prices['output'] ?? 0.0);

        $this->costUsd = ($inputTokens * ($prices['input'] ?? 0.0)
            + ($outputTokens + $thoughtTokens) * $outputRate) / 1_000_000;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(string $model, array $response, bool $producesImage): self
    {
        $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];

        return new self(
            $model,
            (int) ($usage['total_input_tokens'] ?? 0),
            (int) ($usage['total_output_tokens'] ?? 0),
            (int) ($usage['total_thought_tokens'] ?? 0),
            $producesImage,
        );
    }

    /**
     * @return array{model: string, input_tokens: int, output_tokens: int, thought_tokens: int, cost_usd: float}
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'thought_tokens' => $this->thoughtTokens,
            'cost_usd' => round($this->costUsd, 5),
        ];
    }
}
