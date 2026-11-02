<?php

namespace App\Services\FragmentTool;

use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * An uploaded PNG / JPEG decoded safely in memory (nothing is written to disk).
 */
final class SourceImage
{
    private function __construct(
        public readonly GdImage $image,
        public readonly string $name,
    ) {}

    public static function fromUpload(UploadedFile $file): self
    {
        $bytes = (string) file_get_contents($file->getRealPath());

        return self::fromBytes($bytes, pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'image');
    }

    public static function fromBytes(string $bytes, string $name = 'image'): self
    {
        $isPng = str_starts_with($bytes, "\x89PNG\r\n\x1a\n");
        $isJpeg = str_starts_with($bytes, "\xFF\xD8\xFF");

        if (! $isPng && ! $isJpeg) {
            throw new InvalidImageException('Format non supporté : importez une image PNG ou JPG.');
        }

        $size = @getimagesizefromstring($bytes);
        if ($size === false) {
            throw new InvalidImageException('Image illisible ou corrompue.');
        }

        if (config('fragment_tool.max_pixels') < $size[0] * $size[1]) {
            throw new InvalidImageException('Image trop grande (nombre de pixels).');
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new InvalidImageException('Image illisible ou corrompue.');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        if ($isJpeg) {
            $image = self::applyExifOrientation($image, $bytes);
        }

        return new self($image, self::safeName($name));
    }

    public function width(): int
    {
        return imagesx($this->image);
    }

    public function height(): int
    {
        return imagesy($this->image);
    }

    /**
     * Crops then scales down so the long side is at most $maxLongSide (alpha preserved).
     */
    public function crop(CropRect $crop, ?int $maxLongSide = null): GdImage
    {
        $scale = $maxLongSide ? min(1, $maxLongSide / max($crop->width, $crop->height)) : 1;
        $width = max(1, (int) round($crop->width * $scale));
        $height = max(1, (int) round($crop->height * $scale));

        $target = self::blank($width, $height);
        imagecopyresampled($target, $this->image, 0, 0, $crop->x, $crop->y, $width, $height, $crop->width, $crop->height);

        return $target;
    }

    /**
     * Transparent truecolor canvas.
     */
    public static function blank(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        return $image;
    }

    /**
     * Encodes a GD image as PNG bytes.
     */
    public static function toPng(GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * Encodes a GD image as JPEG bytes, flattened on a solid colour.
     *
     * @param  array{0: int, 1: int, 2: int}  $background
     */
    public static function toJpeg(GdImage $image, array $background = [255, 255, 255], int $quality = 92): string
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, ...$background));
        imagealphablending($flat, true);
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        ob_start();
        imagejpeg($flat, null, $quality);

        return (string) ob_get_clean();
    }

    private static function applyExifOrientation(GdImage $image, string $bytes): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return match ($orientation) {
            2 => self::flipped($image, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($image, 180, 0),
            4 => self::flipped($image, IMG_FLIP_VERTICAL),
            5 => self::flipped(imagerotate($image, -90, 0), IMG_FLIP_HORIZONTAL),
            6 => imagerotate($image, -90, 0),
            7 => self::flipped(imagerotate($image, 90, 0), IMG_FLIP_HORIZONTAL),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private static function flipped(GdImage $image, int $mode): GdImage
    {
        imageflip($image, $mode);

        return $image;
    }

    private static function safeName(string $name): string
    {
        $name = preg_replace('/[^\pL\pN_-]+/u', '_', $name) ?? 'image';

        return trim(mb_substr($name, 0, 80), '_') ?: 'image';
    }
}
