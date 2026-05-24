/**
 * DETERMINISTIC IMAGE → PIXEL-ART PIPELINE
 * ────────────────────────────────────────
 * Minimal, predictable, no hidden heuristics.
 *
 *   1. Raw grid extraction   (area-average box filter)
 *   2. Per-cell perceptual match  (CIE76 ΔE in CIELAB, nearest only)
 *   3. Manual overrides win
 *
 * No rescue pass, no denoise, no chroma boost, no harmonisation,
 * no tag bias, no cluster merging, no "pixel-art mode" — the same
 * pipeline produces the same output for the same input, every time.
 */

import {
  PaletteColor,
  buildRawGrid,
  findClosestColor,
  ColorMode,
} from './pixelArt';

export interface PipelineInput {
  imageData: ImageData;
  imageWidth: number;
  imageHeight: number;
  gridCols: number;
  gridRows: number;
  palette: PaletteColor[];
  /** Optional manual overrides — `${row},${col}` → hex */
  manualEdits?: Map<string, string>;
  // The following options are accepted for back-compat with callers
  // but no longer alter the rendering pipeline.
  customMode?: boolean;
  colorMode?: ColorMode;
  preferredTag?: string | null;
  pixelArtMode?: boolean;
}

export interface MappedGrid {
  cols: number;
  rows: number;
  rawColors: [number, number, number][][];
  mapped: PaletteColor[][];
}

export function runPipeline(input: PipelineInput): MappedGrid | null {
  const {
    imageData, imageWidth, imageHeight, gridCols, gridRows, palette,
    manualEdits,
  } = input;

  if (!imageData || gridCols <= 0 || gridRows <= 0) return null;

  const rawColors = buildRawGrid(imageData, imageWidth, imageHeight, gridCols, gridRows, palette);

  const mapped: PaletteColor[][] = [];
  for (let r = 0; r < gridRows; r++) {
    const row: PaletteColor[] = [];
    for (let c = 0; c < gridCols; c++) {
      const rgb = rawColors[r]?.[c] ?? [0, 0, 0];
      const override = manualEdits?.get(`${r},${c}`);
      if (override) {
        const hit = palette.find(p => p.hex.toLowerCase() === override.toLowerCase());
        if (hit) { row.push(hit); continue; }
      }
      row.push(palette.length > 0 ? findClosestColor(rgb, palette) : { hex: '#888888', symbol: '?' });
    }
    mapped.push(row);
  }

  return { cols: gridCols, rows: gridRows, rawColors, mapped };
}
