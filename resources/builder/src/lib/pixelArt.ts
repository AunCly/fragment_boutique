/**
 * MINIMAL DETERMINISTIC PIXEL-ART PIPELINE — CORE
 * ────────────────────────────────────────────────
 * No heuristics. No rescue passes. No denoise. No chroma boost.
 * No hue-sector enforcement. No tag-based bias. No cluster merging
 * during mapping. No automatic harmonisation.
 *
 * One job: for every cell of the source grid, pick the palette entry
 * with the smallest CIE76 ΔE in CIELAB. Manual overrides win.
 */

export interface PaletteColor {
  hex: string;
  symbol: string;
  name?: string;
  textureUrl?: string;
  textureAvgColor?: string;
  tags?: string[];
}

export const DEFAULT_PALETTE: PaletteColor[] = [];

export type ColorMode = 'strict' | 'naturel';

// ─── Color conversion ──────────────────────────────────────────────

export function hexToRgb(hex: string): [number, number, number] {
  const h = hex.replace('#', '');
  return [
    parseInt(h.substring(0, 2), 16),
    parseInt(h.substring(2, 4), 16),
    parseInt(h.substring(4, 6), 16),
  ];
}

export function rgbToHex(r: number, g: number, b: number): string {
  return '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('');
}

function srgbToLinear(c: number): number {
  c /= 255;
  return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
}

function rgbToXyz(r: number, g: number, b: number): [number, number, number] {
  const lr = srgbToLinear(r), lg = srgbToLinear(g), lb = srgbToLinear(b);
  return [
    (0.4124564 * lr + 0.3575761 * lg + 0.1804375 * lb) / 0.95047,
    (0.2126729 * lr + 0.7151522 * lg + 0.0721750 * lb) / 1.00000,
    (0.0193339 * lr + 0.0757381 * lg + 0.9144736 * lb) / 1.08883,
  ];
}

function xyzF(t: number): number {
  return t > 0.008856 ? Math.cbrt(t) : 7.787 * t + 16 / 116;
}

export function rgbToLab(r: number, g: number, b: number): [number, number, number] {
  const [x, y, z] = rgbToXyz(r, g, b);
  const fy = xyzF(y);
  return [
    116 * fy - 16,
    500 * (xyzF(x) - fy),
    200 * (fy - xyzF(z)),
  ];
}

/** CIE76 Delta-E in LAB space — perceptually uniform distance. */
export function labDistance(lab1: [number, number, number], lab2: [number, number, number]): number {
  const dL = lab1[0] - lab2[0];
  const da = lab1[1] - lab2[1];
  const db = lab1[2] - lab2[2];
  return Math.sqrt(dL * dL + da * da + db * db);
}

/** Weighted RGB distance, kept only for legacy grid-line detection. */
export function colorDistance(c1: [number, number, number], c2: [number, number, number]): number {
  const rmean = (c1[0] + c2[0]) / 2;
  const dr = c1[0] - c2[0];
  const dg = c1[1] - c2[1];
  const db = c1[2] - c2[2];
  return Math.sqrt(
    (2 + rmean / 256) * dr * dr +
    4 * dg * dg +
    (2 + (255 - rmean) / 256) * db * db
  );
}

// ─── Palette matching — pure perceptual nearest neighbour ──────────

/** Pure CIE76 ΔE — no weighting, no penalty, no sector rule. */
function nearestIndex(rgb: [number, number, number], paletteLab: [number, number, number][]): number {
  const srcLab = rgbToLab(rgb[0], rgb[1], rgb[2]);
  let best = 0;
  let bestDist = Infinity;
  for (let i = 0; i < paletteLab.length; i++) {
    const d = labDistance(srcLab, paletteLab[i]);
    if (d < bestDist) { bestDist = d; best = i; }
  }
  return best;
}

export function findClosestColor(rgb: [number, number, number], palette: PaletteColor[]): PaletteColor {
  if (palette.length === 0) return { hex: '#888888', symbol: '?' };
  const labs = palette.map(p => {
    const [r, g, b] = hexToRgb(p.hex);
    return rgbToLab(r, g, b);
  });
  return palette[nearestIndex(rgb, labs)];
}

// ─── Color analysis (used only by UI panels, NOT by the renderer) ──

export interface ColorAnalysis {
  distinctColors: number;
  clusters: { rgb: [number, number, number]; hex: string; count: number }[];
  recommendedPaletteSize: number;
}

export function analyzeGridColors(gridColors: [number, number, number][][]): ColorAnalysis {
  if (gridColors.length === 0) return { distinctColors: 0, clusters: [], recommendedPaletteSize: 4 };

  const quantized = new Map<string, { sumR: number; sumG: number; sumB: number; count: number }>();
  for (const row of gridColors) {
    for (const [r, g, b] of row) {
      const qr = Math.round(r / 24) * 24;
      const qg = Math.round(g / 24) * 24;
      const qb = Math.round(b / 24) * 24;
      const key = `${qr},${qg},${qb}`;
      const existing = quantized.get(key);
      if (existing) {
        existing.sumR += r; existing.sumG += g; existing.sumB += b;
        existing.count++;
      } else {
        quantized.set(key, { sumR: r, sumG: g, sumB: b, count: 1 });
      }
    }
  }

  const rawClusters = [...quantized.values()].map(v => ({
    rgb: [Math.round(v.sumR / v.count), Math.round(v.sumG / v.count), Math.round(v.sumB / v.count)] as [number, number, number],
    lab: rgbToLab(Math.round(v.sumR / v.count), Math.round(v.sumG / v.count), Math.round(v.sumB / v.count)),
    count: v.count,
  })).sort((a, b) => b.count - a.count);

  // Merge perceptually very-similar clusters for display only.
  const merged: typeof rawClusters = [];
  const used = new Set<number>();
  for (let i = 0; i < rawClusters.length; i++) {
    if (used.has(i)) continue;
    let { lab, count } = rawClusters[i];
    let sumR = rawClusters[i].rgb[0] * count;
    let sumG = rawClusters[i].rgb[1] * count;
    let sumB = rawClusters[i].rgb[2] * count;
    let totalCount = count;
    for (let j = i + 1; j < rawClusters.length; j++) {
      if (used.has(j)) continue;
      if (labDistance(lab, rawClusters[j].lab) < 9) {
        used.add(j);
        sumR += rawClusters[j].rgb[0] * rawClusters[j].count;
        sumG += rawClusters[j].rgb[1] * rawClusters[j].count;
        sumB += rawClusters[j].rgb[2] * rawClusters[j].count;
        totalCount += rawClusters[j].count;
      }
    }
    const avgR = Math.round(sumR / totalCount);
    const avgG = Math.round(sumG / totalCount);
    const avgB = Math.round(sumB / totalCount);
    merged.push({
      rgb: [avgR, avgG, avgB],
      lab: rgbToLab(avgR, avgG, avgB),
      count: totalCount,
    });
  }

  const clusters = merged.map(c => ({
    rgb: c.rgb,
    hex: rgbToHex(c.rgb[0], c.rgb[1], c.rgb[2]),
    count: c.count,
  }));

  const distinctColors = clusters.length;
  const recommendedPaletteSize = distinctColors <= 4 ? 4
    : distinctColors <= 8 ? 8
    : distinctColors <= 16 ? 16
    : 32;

  return { distinctColors, clusters, recommendedPaletteSize };
}

// ─── Mapping API (kept for back-compat with pipeline.ts) ───────────
//
// The "balanced" map exists ONLY to carry manual overrides. No
// automatic remapping, no rescue, no denoise, no custom-mode repair.
// All extra parameters are accepted but ignored — they used to drive
// removed heuristics.

export function buildBalancedMapping(
  _gridColors: [number, number, number][][],
  _palette: PaletteColor[],
  _customMode: boolean = false,
  _colorMode: ColorMode = 'strict',
  _preferredTag: string | null = null,
  _pixelArtMode: boolean = false,
): Map<string, string> {
  return new Map<string, string>();
}

export function findClosestColorBalanced(
  rgb: [number, number, number],
  palette: PaletteColor[],
  row: number,
  col: number,
  balancedMap?: Map<string, string>,
  _colorMode: ColorMode = 'strict',
  _preferredTag: string | null = null,
  _pixelArtMode: boolean = false,
): PaletteColor {
  if (balancedMap) {
    const override = balancedMap.get(`${row},${col}`);
    if (override) {
      const hit = palette.find(c => c.hex.toLowerCase() === override.toLowerCase());
      if (hit) return hit;
    }
  }
  return findClosestColor(rgb, palette);
}

// ─── Grid-line detection (used by ImageImporter to guess cell count) ─

export interface GridDetectionResult {
  cols: number;
  rows: number;
  verticalLines: number[];
  horizontalLines: number[];
}

export function detectGridSize(
  imageData: ImageData,
  width: number,
  height: number
): GridDetectionResult {
  const d = imageData.data;
  const luminance = (r: number, g: number, b: number) => 0.299 * r + 0.587 * g + 0.114 * b;
  const pixelLum = (x: number, y: number) => {
    const i = (y * width + x) * 4;
    return luminance(d[i], d[i + 1], d[i + 2]);
  };

  const computeLineScores = (
    primaryLen: number,
    secondaryLen: number,
    getLum: (primary: number, secondary: number) => number
  ): number[] => {
    const numSamples = Math.min(secondaryLen, 80);
    const step = secondaryLen / numSamples;
    const samples: number[] = [];
    for (let i = 0; i < numSamples; i++) samples.push(Math.floor(i * step + step / 2));
    const scores: number[] = new Array(primaryLen).fill(0);
    const offset = 2;
    for (let p = offset; p < primaryLen - offset; p++) {
      let totalDiff = 0;
      for (const s of samples) {
        const here = getLum(p, s);
        const left = getLum(p - offset, s);
        const right = getLum(p + offset, s);
        const avgNeighbor = (left + right) / 2;
        totalDiff += Math.max(0, avgNeighbor - here);
      }
      scores[p] = totalDiff / samples.length;
    }
    return scores;
  };

  const computeRegularity = (gaps: number[]): { score: number; dominantGap: number } => {
    if (gaps.length < 2) return { score: 0, dominantGap: 0 };
    const gapCounts = new Map<number, number>();
    for (const g of gaps) {
      const rounded = Math.round(g);
      gapCounts.set(rounded, (gapCounts.get(rounded) || 0) + 1);
    }
    let dominantGap = 0, maxCount = 0;
    for (const [gap, count] of gapCounts) {
      const total = count + (gapCounts.get(gap - 1) || 0) + (gapCounts.get(gap + 1) || 0);
      if (total > maxCount) { maxCount = total; dominantGap = gap; }
    }
    const matching = gaps.filter(g => Math.abs(g - dominantGap) <= 1.5).length;
    return { score: matching / gaps.length, dominantGap };
  };

  const findGridLines = (scores: number[]): number[] => {
    if (scores.length < 10) return [];
    const sorted = [...scores].sort((a, b) => a - b);
    const median = sorted[Math.floor(sorted.length * 0.5)];
    const p90 = sorted[Math.floor(sorted.length * 0.9)];
    if (p90 < 2) return [];
    const thresholds = [
      median + (p90 - median) * 0.3,
      median + (p90 - median) * 0.5,
      median + (p90 - median) * 0.7,
    ];
    let bestLines: number[] = [];
    let bestRegularity = 0;
    for (const threshold of thresholds) {
      const peaks: number[] = [];
      for (let i = 2; i < scores.length - 2; i++) {
        if (scores[i] >= threshold && scores[i] >= scores[i - 1] && scores[i] >= scores[i + 1]) {
          if (peaks.length > 0 && i - peaks[peaks.length - 1] <= 2) {
            if (scores[i] > scores[peaks[peaks.length - 1]]) peaks[peaks.length - 1] = i;
          } else peaks.push(i);
        }
      }
      if (peaks.length < 3) continue;
      const gaps: number[] = [];
      for (let i = 1; i < peaks.length; i++) gaps.push(peaks[i] - peaks[i - 1]);
      const regularity = computeRegularity(gaps);
      if (regularity.score > bestRegularity) {
        bestRegularity = regularity.score;
        bestLines = peaks;
      }
    }
    return bestLines;
  };

  const linesToCellCount = (lines: number[], totalLen: number): number => {
    if (lines.length < 2) return Math.min(32, totalLen);
    const gaps: number[] = [];
    for (let i = 1; i < lines.length; i++) gaps.push(lines[i] - lines[i - 1]);
    const { dominantGap } = computeRegularity(gaps);
    if (dominantGap < 3) return Math.min(32, totalLen);
    let cellCount = lines.length - 1;
    const spaceBeforeFirst = lines[0];
    if (spaceBeforeFirst >= dominantGap * 0.6) cellCount += Math.round(spaceBeforeFirst / dominantGap);
    const spaceAfterLast = totalLen - 1 - lines[lines.length - 1];
    if (spaceAfterLast >= dominantGap * 0.6) cellCount += Math.round(spaceAfterLast / dominantGap);
    return Math.max(2, Math.min(256, cellCount));
  };

  const vScores = computeLineScores(width, height, (x, y) => pixelLum(x, y));
  const verticalLines = findGridLines(vScores);
  const hScores = computeLineScores(height, width, (y, x) => pixelLum(x, y));
  const horizontalLines = findGridLines(hScores);
  const cols = linesToCellCount(verticalLines, width);
  const rows = linesToCellCount(horizontalLines, height);
  return { cols, rows, verticalLines, horizontalLines };
}

// ─── Grid sampling — clean box-averaging downsample ────────────────
//
// Each output cell averages exactly the source pixels it covers.
// No JPEG-noise filtering, no chroma weighting, no bucket trick.
// This is a standard area-resampling kernel — deterministic and
// faithful to the original.

export function extractGridColors(
  imageData: ImageData,
  imgWidth: number,
  imgHeight: number,
  cols: number,
  rows: number
): [number, number, number][][] {
  const d = imageData.data;
  const grid: [number, number, number][][] = [];

  // Geometric hybrid sampling rule:
  //   - If each output cell covers < 4 source pixels (cellW * cellH < 4),
  //     use nearest-neighbor (read the center pixel). This avoids color
  //     bleeding between cells when the source is already at or near the
  //     target resolution (true pixel-art case).
  //   - Otherwise use a standard area-average box filter (photo / high-res
  //     case). Deterministic, no content heuristic, no toggle.
  const cellW = imgWidth / cols;
  const cellH = imgHeight / rows;
  const useNearest = cellW * cellH < 4;

  for (let row = 0; row < rows; row++) {
    const rowColors: [number, number, number][] = [];
    const y0 = (row * imgHeight) / rows;
    const y1 = ((row + 1) * imgHeight) / rows;
    const yStart = Math.floor(y0);
    const yEnd = Math.max(yStart + 1, Math.ceil(y1));
    for (let col = 0; col < cols; col++) {
      const x0 = (col * imgWidth) / cols;
      const x1 = ((col + 1) * imgWidth) / cols;

      if (useNearest) {
        const cx = Math.min(imgWidth - 1, Math.floor((x0 + x1) / 2));
        const cy = Math.min(imgHeight - 1, Math.floor((y0 + y1) / 2));
        const i = (cy * imgWidth + cx) * 4;
        rowColors.push([d[i], d[i + 1], d[i + 2]]);
        continue;
      }

      const xStart = Math.floor(x0);
      const xEnd = Math.max(xStart + 1, Math.ceil(x1));
      let r = 0, g = 0, b = 0, n = 0;
      for (let y = yStart; y < yEnd && y < imgHeight; y++) {
        for (let x = xStart; x < xEnd && x < imgWidth; x++) {
          const i = (y * imgWidth + x) * 4;
          r += d[i]; g += d[i + 1]; b += d[i + 2]; n++;
        }
      }
      if (n === 0) {
        const cx = Math.min(imgWidth - 1, Math.floor((x0 + x1) / 2));
        const cy = Math.min(imgHeight - 1, Math.floor((y0 + y1) / 2));
        const i = (cy * imgWidth + cx) * 4;
        rowColors.push([d[i], d[i + 1], d[i + 2]]);
      } else {
        rowColors.push([Math.round(r / n), Math.round(g / n), Math.round(b / n)]);
      }
    }
    grid.push(rowColors);
  }
  return grid;
}

export function padGridColors(
  grid: [number, number, number][][],
  srcCols: number,
  srcRows: number,
  targetCols: number,
  targetRows: number,
): [number, number, number][][] {
  const result: [number, number, number][][] = [];
  const offsetX = Math.floor((targetCols - srcCols) / 2);
  const offsetY = Math.floor((targetRows - srcRows) / 2);
  for (let row = 0; row < targetRows; row++) {
    const rowColors: [number, number, number][] = [];
    for (let col = 0; col < targetCols; col++) {
      const srcRow = Math.max(0, Math.min(srcRows - 1, row - offsetY));
      const srcCol = Math.max(0, Math.min(srcCols - 1, col - offsetX));
      rowColors.push(grid[srcRow]?.[srcCol] ?? [0, 0, 0]);
    }
    result.push(rowColors);
  }
  return result;
}

// ─── Pixel-art integrity helpers ───────────────────────────────────

export function readPixelsDirect(
  imageData: ImageData,
  width: number,
  height: number,
): [number, number, number][][] {
  const d = imageData.data;
  const out: [number, number, number][][] = [];
  for (let y = 0; y < height; y++) {
    const row: [number, number, number][] = [];
    for (let x = 0; x < width; x++) {
      const i = (y * width + x) * 4;
      row.push([d[i], d[i + 1], d[i + 2]]);
    }
    out.push(row);
  }
  return out;
}

export function padWithFrameColor(
  grid: [number, number, number][][],
  srcCols: number,
  srcRows: number,
  targetCols: number,
  targetRows: number,
  frameRgb: [number, number, number],
): [number, number, number][][] {
  const offsetX = Math.floor((targetCols - srcCols) / 2);
  const offsetY = Math.floor((targetRows - srcRows) / 2);
  const out: [number, number, number][][] = [];
  for (let row = 0; row < targetRows; row++) {
    const rowColors: [number, number, number][] = [];
    for (let col = 0; col < targetCols; col++) {
      const sr = row - offsetY;
      const sc = col - offsetX;
      if (sr >= 0 && sr < srcRows && sc >= 0 && sc < srcCols) rowColors.push(grid[sr][sc]);
      else rowColors.push([frameRgb[0], frameRgb[1], frameRgb[2]]);
    }
    out.push(rowColors);
  }
  return out;
}

export function pickFrameColor(palette: PaletteColor[]): [number, number, number] {
  if (palette.length === 0) return [128, 128, 128];
  let bestIdx = 0, bestChroma = Infinity;
  for (let i = 0; i < palette.length; i++) {
    const [r, g, b] = hexToRgb(palette[i].hex);
    const [, a, bb] = rgbToLab(r, g, b);
    const chroma = Math.sqrt(a * a + bb * bb);
    if (chroma < bestChroma) { bestChroma = chroma; bestIdx = i; }
  }
  return hexToRgb(palette[bestIdx].hex);
}

export function buildRawGrid(
  imageData: ImageData,
  imgWidth: number,
  imgHeight: number,
  cols: number,
  rows: number,
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  _palette: PaletteColor[],
): [number, number, number][][] {
  if (imgWidth === cols && imgHeight === rows) {
    return readPixelsDirect(imageData, imgWidth, imgHeight);
  }
  return extractGridColors(imageData, imgWidth, imgHeight, cols, rows);
}
