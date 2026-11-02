<?php

namespace App\Services\FragmentTool\Rendering;

use App\Services\FragmentTool\Essence;
use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\SourceImage;
use App\Services\FragmentTool\ToolPalette;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Wood render: each cell is drawn with a piece of the photo of its essence, grain
 * oriented the same way (vertical) for all essences. Essences without a photo are
 * rendered flat.
 */
class WoodRenderer
{
    /** A piece shows this share of the short side of the photo. */
    private const PIECE_SHARE = 0.2;

    /** Maximum brightness variation between pieces. */
    private const BRIGHTNESS_VARIATION = 0.035;

    private const SEED = 20261102;

    /** @var array<string, array{0: GdImage, 1: GdImage}|null> photo and its 180° rotation, by path */
    private static array $textures = [];

    public function render(Grid $grid, ToolPalette $palette): string
    {
        $cell = max(4, intdiv(config('fragment_tool.wood_long_side_px'), max($grid->cols, $grid->rows)));
        $image = imagecreatetruecolor($grid->cols * $cell, $grid->rows * $cell);
        imagealphablending($image, true);
        $random = new Randomizer(new Mt19937(self::SEED));
        $joint = imagecolorallocatealpha($image, 0, 0, 0, 85);
        $flat = [];

        foreach ($grid->cells as $row => $line) {
            foreach ($line as $col => $id) {
                $essence = $palette->get($id);
                $x = $col * $cell;
                $y = $row * $cell;
                $texture = $this->texture($essence);

                if ($texture === null) {
                    $flat[$id] ??= imagecolorallocate($image, ...$essence->rgb);
                    imagefilledrectangle($image, $x, $y, $x + $cell - 1, $y + $cell - 1, $flat[$id]);
                } else {
                    $this->drawPiece($image, $texture[$random->getInt(0, 3) === 0 ? 1 : 0], $x, $y, $cell, $random);
                }

                imageline($image, $x + $cell - 1, $y, $x + $cell - 1, $y + $cell - 1, $joint);
                imageline($image, $x, $y + $cell - 1, $x + $cell - 2, $y + $cell - 1, $joint);
            }
        }

        return SourceImage::toPng($image);
    }

    /**
     * Ids of the palette essences that have a photo available.
     *
     * @return list<int>
     */
    public function essencesWithTexture(ToolPalette $palette): array
    {
        return array_values(array_map(
            fn (Essence $essence) => $essence->id,
            array_filter($palette->all(), fn (Essence $essence) => $this->texture($essence) !== null),
        ));
    }

    private function drawPiece(GdImage $image, GdImage $texture, int $x, int $y, int $cell, Randomizer $random): void
    {
        $width = imagesx($texture);
        $height = imagesy($texture);
        $piece = max(1, (int) floor(min($width, $height) * self::PIECE_SHARE));
        $sx = $random->getInt(0, max(0, $width - $piece));
        $sy = $random->getInt(0, max(0, $height - $piece));

        imagecopyresampled($image, $texture, $x, $y, $sx, $sy, $cell, $cell, $piece, $piece);

        $variation = ($random->getFloat(0, 1) * 2 - 1) * self::BRIGHTNESS_VARIATION;
        $overlay = $variation >= 0
            ? imagecolorallocatealpha($image, 255, 255, 255, 127 - (int) round(127 * $variation))
            : imagecolorallocatealpha($image, 0, 0, 0, 127 - (int) round(127 * -$variation));
        imagefilledrectangle($image, $x, $y, $x + $cell - 1, $y + $cell - 1, $overlay);
    }

    /**
     * @return array{0: GdImage, 1: GdImage}|null
     */
    private function texture(Essence $essence): ?array
    {
        if ($essence->texturePath === null) {
            return null;
        }

        if (array_key_exists($essence->texturePath, self::$textures)) {
            return self::$textures[$essence->texturePath];
        }

        $disk = Storage::disk('public');
        $bytes = $disk->exists($essence->texturePath) ? $disk->get($essence->texturePath) : null;
        $photo = $bytes ? @imagecreatefromstring($bytes) : false;

        if ($photo === false) {
            return self::$textures[$essence->texturePath] = null;
        }

        if (! imageistruecolor($photo)) {
            imagepalettetotruecolor($photo);
        }

        if ($this->hasHorizontalGrain($photo)) {
            $photo = imagerotate($photo, 90, 0);
        }

        return self::$textures[$essence->texturePath] = [$photo, imagerotate($photo, 180, 0)];
    }

    /**
     * Vertical grain = colour varies more across x than along y.
     */
    public function hasHorizontalGrain(GdImage $photo): bool
    {
        $size = 64;
        $small = imagecreatetruecolor($size, $size);
        imagecopyresampled($small, $photo, 0, 0, 0, 0, $size, $size, imagesx($photo), imagesy($photo));
        $gray = static fn (int $c): int => (($c >> 16) & 0xFF) * 299 + (($c >> 8) & 0xFF) * 587 + ($c & 0xFF) * 114;

        $acrossX = $acrossY = 0;
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $here = $gray(imagecolorat($small, $x, $y));
                $acrossX += abs($here - $gray(imagecolorat($small, $x + 1, $y)));
                $acrossY += abs($here - $gray(imagecolorat($small, $x, $y + 1)));
            }
        }

        return $acrossY > $acrossX;
    }

    public static function flushCache(): void
    {
        self::$textures = [];
    }
}
