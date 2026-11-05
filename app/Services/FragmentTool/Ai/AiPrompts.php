<?php

namespace App\Services\FragmentTool\Ai;

use App\Services\FragmentTool\ToolPalette;

/**
 * Prompts sent to Gemini. The Fragment constraints (exact grid, essences) are never
 * required from the model: they are imposed by the projection afterwards.
 */
class AiPrompts
{
    /** @var array<string, string> style variants used from 2 images per batch */
    public const STYLES = [
        'base' => '',
        'contrast' => 'Use strong contrast between the subject\'s areas: clearly separated light and dark masses.',
        'faithful' => 'Stay as faithful as possible to the original: same pose, proportions and colour layout.',
        'cute' => 'Give it a cute, friendly "kawaii" look: rounder shapes, expressive simple eyes.',
    ];

    /**
     * Styles for a batch of $count images; beyond the number of styles, they are reused in turn.
     *
     * @return list<string>
     */
    public static function stylesFor(int $count): array
    {
        $styles = array_keys(self::STYLES);

        return array_map(fn (int $index) => $styles[$index % count($styles)], range(0, max(1, $count) - 1));
    }

    /**
     * Grid size asked to the model: it nearly always draws a grid about twice as fine
     * as requested, so we ask for N/2 to get N.
     *
     * @return array{0: int, 1: int}
     */
    public static function requestedGrid(int $cols, int $rows): array
    {
        return [max(4, intdiv($cols, 2)), max(4, intdiv($rows, 2))];
    }

    /**
     * Closest aspect ratio supported by the image model; the projection stretches
     * the result back to the exact format ratio.
     */
    public static function aspectRatio(int $cols, int $rows): string
    {
        $ratio = $cols / $rows;

        return match (true) {
            $ratio >= 1.5 => '16:9',
            $ratio <= 1 / 1.5 => '9:16',
            default => '1:1',
        };
    }

    /**
     * The outline instruction (dark line + light halo around the subject) can be turned off by the user.
     */
    public static function generation(int $cols, int $rows, ToolPalette $palette, int $backgroundId, int $haloId, string $style, ?string $hint, bool $outline = true): string
    {
        [$gridCols, $gridRows] = self::requestedGrid($cols, $rows);
        $background = $palette->get($backgroundId)->hex;
        $halo = $palette->get($haloId)->hex;
        $outlineHex = $palette->get($palette->darkestId())->hex;

        return trim(implode("\n", array_filter([
            'Redraw the subject of this image as simple pixel art, like an illustrator would: simplified shapes, bold readable forms, no superfluous details.',
            "Use a grid of {$gridCols} × {$gridRows} large square pixels covering the whole image. Flat colours only: no gradients, no dithering, no anti-aliasing, no text.",
            'Use warm natural wood tones (light beige to dark brown).',
            "Background: one plain uniform colour {$background} everywhere around the subject.",
            $outline ? "Outline the subject with a continuous dark brown line ({$outlineHex}), itself surrounded by a continuous light outline ({$halo}) that separates it from the background." : null,
            self::STYLES[$style] ?? '',
            $hint ? "Additional instructions from the user: {$hint}" : null,
        ])));
    }

    /**
     * @param  list<string>  $remarks
     */
    public static function refine(int $cols, int $rows, ToolPalette $palette, int $backgroundId, int $haloId, array $remarks, ?string $hint, bool $outline = true): string
    {
        [$gridCols, $gridRows] = self::requestedGrid($cols, $rows);
        $background = $palette->get($backgroundId)->hex;
        $halo = $palette->get($haloId)->hex;
        $lines = implode("\n", array_map(fn (string $remark) => "- {$remark}", $remarks));

        return trim(implode("\n", array_filter([
            'The first image is the original. The second image is a pixel art version of it that must be corrected.',
            "Redraw the pixel art version fixing these problems, and keep everything else identical:\n{$lines}",
            "Keep a grid of {$gridCols} × {$gridRows} large square pixels, flat colours, wood tones, plain background {$background}"
                .($outline ? ", continuous dark brown outline surrounded by a continuous light outline {$halo}." : '.'),
            $hint ? "Additional instructions from the user: {$hint}" : null,
        ])));
    }

    public static function judge(int $count, int $backgroundId, int $haloId, ToolPalette $palette, bool $outline = true): string
    {
        $background = $palette->get($backgroundId)->hex;
        $halo = $palette->get($haloId)->hex;
        $max = config('fragment_tool.ai.max_problems');

        return implode("\n", [
            "The first image is the original. The {$count} following images are candidate pixel art versions made of wood pieces, numbered from 0 in order.",
            'Rate each candidate from 0 to 10 for how recognisable and pleasant the subject is once made in wood.',
            "Criteria: subject readable at this size, clean shapes, eyes and key details visible, plain background {$background}"
                .($outline ? ", continuous dark outline with a light outline {$halo} around the subject." : '.'),
            "List at most {$max} concrete problems per candidate (short sentences in French, e.g. \"l'œil gauche a disparu\"). Give the index of your preferred candidate.",
        ]);
    }

    /**
     * @param  list<list<string>>  $codes  essence code per cell
     */
    public static function suggest(array $codes, ToolPalette $palette): string
    {
        $legend = implode("\n", array_map(
            fn ($essence) => "{$essence->code} = {$essence->name} ({$essence->hex})",
            array_values($palette->all()),
        ));
        $grid = implode("\n", array_map(fn (array $row) => implode(' ', $row), $codes));
        $rows = count($codes);
        $cols = count($codes[0]);
        $max = config('fragment_tool.ai.max_suggestions');

        return implode("\n", [
            "The first image is the original. The second image is a {$cols} × {$rows} wood-piece pixel art made from it. Its grid, row by row from the top (row 1) and column by column from the left (column 1), uses these essence codes:",
            $legend,
            'Grid:',
            $grid,
            "Suggest at most {$max} single-cell corrections that would make the subject more recognisable (missing eye, broken outline, stray piece…). Each one: row, column, new essence code and a short reason in French. Only suggest real changes.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function judgeSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'candidates' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'index' => ['type' => 'integer'],
                            'score' => ['type' => 'number'],
                            'problems' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['index', 'score', 'problems'],
                    ],
                ],
                'preferred' => ['type' => 'integer'],
            ],
            'required' => ['candidates', 'preferred'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function suggestSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'suggestions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'row' => ['type' => 'integer'],
                            'col' => ['type' => 'integer'],
                            'code' => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['row', 'col', 'code', 'reason'],
                    ],
                ],
            ],
            'required' => ['suggestions'],
        ];
    }
}
