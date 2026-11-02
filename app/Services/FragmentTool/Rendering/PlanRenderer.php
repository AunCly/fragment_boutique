<?php

namespace App\Services\FragmentTool\Rendering;

use App\Services\FragmentTool\Color;
use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\SourceImage;
use App\Services\FragmentTool\ToolPalette;
use GdImage;

/**
 * Assembly plan: numbered coloured grid with thick markers, and a legend with the
 * quantity of pieces per essence.
 */
class PlanRenderer
{
    private const MARGIN = 40;

    private const HEADER_HEIGHT = 90;

    private const LEGEND_LINE = 34;

    private const MIN_WIDTH = 560;

    /** Total grid size the cells shrink to fit for large formats. */
    private const MAX_GRID_PX = 2400;

    private const MIN_CELL_PX = 22;

    public function render(Grid $grid, ToolPalette $palette, string $sourceName, ?string $model = null): string
    {
        $cell = max(self::MIN_CELL_PX, min(config('fragment_tool.plan_cell_px'), intdiv(self::MAX_GRID_PX, max($grid->cols, $grid->rows))));
        $gridWidth = $grid->cols * $cell;
        $gridHeight = $grid->rows * $cell;
        $counts = $grid->counts();
        $legendHeight = (count($counts) + 2) * self::LEGEND_LINE + self::MARGIN;

        $width = max(self::MIN_WIDTH, $gridWidth + 2 * self::MARGIN);
        $height = self::HEADER_HEIGHT + $gridHeight + self::MARGIN + $legendHeight;

        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $grey = imagecolorallocate($image, 110, 110, 110);
        imagefill($image, 0, 0, $white);

        $font = config('fragment_tool.font_path');
        $bold = config('fragment_tool.bold_font_path');

        $this->text($image, 20, self::MARGIN, 44, $black, $bold, $sourceName);
        $subtitle = "Format {$grid->cols} × {$grid->rows}".($palette->name ? " · {$palette->name}" : '').($model ? " · IA : {$model}" : '');
        $this->text($image, 13, self::MARGIN, 72, $grey, $font, $subtitle);

        $left = intdiv($width - $gridWidth, 2);
        $top = self::HEADER_HEIGHT;
        $this->drawGrid($image, $grid, $palette, $left, $top, $cell, $font);

        $y = $top + $gridHeight + self::MARGIN;
        foreach ($counts as $id => $count) {
            $essence = $palette->get($id);
            $this->text($image, 13, self::MARGIN, $y + 22, $black, $bold, $essence->code);
            imagefilledrectangle($image, self::MARGIN + 50, $y + 4, self::MARGIN + 76, $y + 28, imagecolorallocate($image, ...$essence->rgb));
            imagerectangle($image, self::MARGIN + 50, $y + 4, self::MARGIN + 76, $y + 28, $grey);
            $this->text($image, 13, self::MARGIN + 90, $y + 22, $black, $font, $essence->name);
            $this->text($image, 12, self::MARGIN + 290, $y + 22, $grey, $font, $essence->hex);
            $this->textRight($image, 13, $width - self::MARGIN, $y + 22, $black, $bold, $count.' pièce'.($count > 1 ? 's' : ''));
            $y += self::LEGEND_LINE;
        }

        imageline($image, self::MARGIN, $y + 6, $width - self::MARGIN, $y + 6, $grey);
        $total = $grid->cols * $grid->rows;
        $this->text($image, 13, self::MARGIN, $y + 30, $black, $bold, 'Total');
        $this->textRight($image, 13, $width - self::MARGIN, $y + 30, $black, $bold, "{$total} pièces");

        return SourceImage::toPng($image);
    }

    /**
     * Thick marker lines every 4 cells for small formats, every 8 otherwise.
     */
    public static function markerEvery(Grid $grid): int
    {
        return max($grid->cols, $grid->rows) <= 16 ? 4 : 8;
    }

    private function drawGrid(GdImage $image, Grid $grid, ToolPalette $palette, int $left, int $top, int $cell, string $font): void
    {
        $fills = [];
        $thin = imagecolorallocatealpha($image, 0, 0, 0, 90);
        $thick = imagecolorallocate($image, 30, 30, 30);
        $fontSize = max(7, (int) round($cell * 0.32));

        foreach ($grid->cells as $row => $line) {
            foreach ($line as $col => $id) {
                $essence = $palette->get($id);
                $fills[$id] ??= imagecolorallocate($image, ...$essence->rgb);
                $x = $left + $col * $cell;
                $y = $top + $row * $cell;
                imagefilledrectangle($image, $x, $y, $x + $cell - 1, $y + $cell - 1, $fills[$id]);

                $textColor = $this->readableOn($essence->rgb) === 'white'
                    ? imagecolorallocate($image, 255, 255, 255)
                    : imagecolorallocate($image, 0, 0, 0);
                $this->textCentred($image, $fontSize, $x + $cell / 2, $y + $cell / 2, $textColor, $font, $essence->code);
            }
        }

        imagealphablending($image, true);
        for ($col = 0; $col <= $grid->cols; $col++) {
            imageline($image, $left + $col * $cell, $top, $left + $col * $cell, $top + $grid->rows * $cell, $thin);
        }
        for ($row = 0; $row <= $grid->rows; $row++) {
            imageline($image, $left, $top + $row * $cell, $left + $grid->cols * $cell, $top + $row * $cell, $thin);
        }

        $every = self::markerEvery($grid);
        imagesetthickness($image, 3);
        for ($col = 0; $col <= $grid->cols; $col += $every) {
            imageline($image, $left + $col * $cell, $top, $left + $col * $cell, $top + $grid->rows * $cell, $thick);
        }
        for ($row = 0; $row <= $grid->rows; $row += $every) {
            imageline($image, $left, $top + $row * $cell, $left + $grid->cols * $cell, $top + $row * $cell, $thick);
        }
        imagesetthickness($image, 3);
        imagerectangle($image, $left, $top, $left + $grid->cols * $cell, $top + $grid->rows * $cell, $thick);
        imagesetthickness($image, 1);
    }

    /**
     * Text colour with the best contrast on a background (e.g. white on dark woods).
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    public function readableOn(array $rgb): string
    {
        $luminance = Color::luminance($rgb);

        return (1.05 / ($luminance + 0.05)) > (($luminance + 0.05) / 0.05) ? 'white' : 'black';
    }

    private function text(GdImage $image, float $size, int $x, int $y, int $color, string $font, string $text): void
    {
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    }

    private function textRight(GdImage $image, float $size, int $right, int $y, int $color, string $font, string $text): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        imagettftext($image, $size, 0, $right - ($box[2] - $box[0]), $y, $color, $font, $text);
    }

    private function textCentred(GdImage $image, float $size, float $cx, float $cy, int $color, string $font, string $text): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $width = $box[2] - $box[0];
        $height = $box[1] - $box[7];
        imagettftext($image, $size, 0, (int) round($cx - $width / 2 - $box[0]), (int) round($cy + $height / 2 - $box[1]), $color, $font, $text);
    }
}
