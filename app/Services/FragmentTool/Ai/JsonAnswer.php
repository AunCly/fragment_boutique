<?php

namespace App\Services\FragmentTool\Ai;

/**
 * Tolerant reading of the JSON answers of the models (```json fences, text around).
 */
class JsonAnswer
{
    /**
     * @return array<string, mixed>
     */
    public static function parse(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new GeminiException('Réponse JSON de Gemini illisible.');
    }

    public static function score(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round(max(0.0, min(10.0, (float) $value)), 1);
    }
}
