<?php

namespace App\Services\FragmentTool;

use App\Models\WoodTexture;

/**
 * A wood essence as used by the Fragment tool (immutable snapshot of a WoodTexture).
 */
final readonly class Essence
{
    /** @var array{0: int, 1: int, 2: int} */
    public array $rgb;

    /** @var array{0: float, 1: float, 2: float} */
    public array $lab;

    public function __construct(
        public int $id,
        public string $slug,
        public string $code,
        public string $name,
        public string $hex,
        public ?string $texturePath = null,
    ) {
        $this->rgb = Color::hexToRgb($hex);
        $this->lab = Color::rgbToLab($this->rgb);
    }

    public static function fromWoodTexture(WoodTexture $texture): self
    {
        return new self(
            id: $texture->id,
            slug: $texture->slug,
            code: $texture->grid_code ?: (string) $texture->id,
            name: $texture->name,
            hex: strtolower($texture->hex),
            texturePath: $texture->texture_path,
        );
    }

    /**
     * @return array{id: int, code: string, name: string, hex: string, hasTexture: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'hex' => $this->hex,
            'hasTexture' => $this->texturePath !== null,
        ];
    }
}
