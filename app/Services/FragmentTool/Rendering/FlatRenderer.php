<?php

namespace App\Services\FragmentTool\Rendering;

use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\SourceImage;
use App\Services\FragmentTool\ToolPalette;

/**
 * Enlarged PNG with crisp cells, exact palette colours and no transparency.
 */
class FlatRenderer
{
    public function render(Grid $grid, ToolPalette $palette, ?int $cellPx = null): string
    {
        $cell = $cellPx ?? config('fragment_tool.flat_cell_px');
        $image = imagecreatetruecolor($grid->cols * $cell, $grid->rows * $cell);
        $colors = [];

        foreach ($grid->cells as $row => $line) {
            foreach ($line as $col => $id) {
                $colors[$id] ??= imagecolorallocate($image, ...$palette->get($id)->rgb);
                imagefilledrectangle($image, $col * $cell, $row * $cell, ($col + 1) * $cell - 1, ($row + 1) * $cell - 1, $colors[$id]);
            }
        }

        return SourceImage::toPng($image);
    }
}
