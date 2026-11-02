<?php

namespace App\Services\FragmentTool;

use App\Models\Palette;
use App\Models\WoodTexture;
use InvalidArgumentException;

/**
 * The essences available for one Fragment creation, with the tool defaults
 * (background, halo, motif essence) resolved against them.
 */
final class ToolPalette
{
    /** @var array<int, Essence> */
    private array $essences = [];

    /**
     * @param  list<Essence>  $essences
     */
    public function __construct(array $essences, public readonly string $name = '')
    {
        foreach ($essences as $essence) {
            $this->essences[$essence->id] = $essence;
        }

        if (count($this->essences) < Palette::MIN_ESSENCES) {
            throw new InvalidArgumentException('La palette doit contenir au moins '.Palette::MIN_ESSENCES.' essences.');
        }
    }

    public static function fromPalette(Palette $palette): self
    {
        return new self(
            $palette->woodTextures->map(fn (WoodTexture $texture) => Essence::fromWoodTexture($texture))->values()->all(),
            $palette->name,
        );
    }

    /**
     * @return array<int, Essence>
     */
    public function all(): array
    {
        return $this->essences;
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_keys($this->essences);
    }

    public function has(int $id): bool
    {
        return isset($this->essences[$id]);
    }

    public function get(int $id): Essence
    {
        return $this->essences[$id] ?? throw new InvalidArgumentException("Essence {$id} absente de la palette.");
    }

    public function darkestId(): int
    {
        return $this->sortedByLightness()[0]->id;
    }

    public function lightestId(): int
    {
        $sorted = $this->sortedByLightness();

        return end($sorted)->id;
    }

    public function defaultBackgroundId(): int
    {
        return $this->idForSlug(config('fragment_tool.default_background_slug')) ?? $this->lightestId();
    }

    public function haloId(): int
    {
        return $this->idForSlug(config('fragment_tool.halo_slug')) ?? $this->lightestId();
    }

    public function defaultMotifId(): int
    {
        $sorted = $this->sortedByLightness();

        return $this->idForSlug(config('fragment_tool.default_motif_slug')) ?? $sorted[intdiv(count($sorted), 2)]->id;
    }

    /**
     * Lab lightness range covered by the palette.
     *
     * @return array{0: float, 1: float}
     */
    public function lightnessRange(): array
    {
        $lightness = array_map(fn (Essence $essence) => $essence->lab[0], $this->essences);

        return [min($lightness), max($lightness)];
    }

    /**
     * @return list<array{id: int, code: string, name: string, hex: string, hasTexture: bool}>
     */
    public function toArray(): array
    {
        return array_values(array_map(fn (Essence $essence) => $essence->toArray(), $this->essences));
    }

    /**
     * @return list<Essence>
     */
    private function sortedByLightness(): array
    {
        $essences = array_values($this->essences);
        usort($essences, fn (Essence $a, Essence $b) => $a->lab[0] <=> $b->lab[0]);

        return $essences;
    }

    private function idForSlug(?string $slug): ?int
    {
        foreach ($this->essences as $essence) {
            if ($essence->slug === $slug) {
                return $essence->id;
            }
        }

        return null;
    }
}
