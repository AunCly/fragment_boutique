/**
 * PIXEL-ART DETECTION
 * ───────────────────
 * Detects whether the imported image is an upscaled pixel-art and, if so,
 * returns its logical (true) resolution and the integer upscale factor.
 *
 * Algorithm (deterministic, conservative):
 *   1. Sample N horizontal and N vertical scanlines.
 *   2. On each scanline, measure the length of every "run" of strictly
 *      identical adjacent pixels (exact RGB equality).
 *   3. For each candidate factor K in [2..MAX_K] that divides both image
 *      dimensions, count the proportion of runs whose length is an exact
 *      multiple of K. The largest K with proportion ≥ THRESHOLD wins.
 *   4. Require a minimum number of color transitions per line so flat
 *      images (large uniform fills) are NOT misclassified as pixel-art.
 *
 * No content heuristic, no learning, no fuzzy matching — pure geometry.
 */

export interface PixelArtDetection {
  isPixelArt: boolean;
  logicalWidth: number;
  logicalHeight: number;
  upscaleFactor: number;
}

const MAX_K = 32;
const SCANLINE_COUNT = 16;
const MIN_RUNS_PER_LINE = 4;
const MATCH_THRESHOLD = 0.85;

export function detectPixelArt(
  imageData: ImageData,
  width: number,
  height: number,
): PixelArtDetection {
  const fallback: PixelArtDetection = {
    isPixelArt: false,
    logicalWidth: width,
    logicalHeight: height,
    upscaleFactor: 1,
  };
  if (width < 8 || height < 8) return fallback;
  const d = imageData.data;

  // Collect run lengths across horizontal scanlines.
  const collectRuns = (lineCount: number, primaryLen: number, secondaryLen: number, getIdx: (p: number, s: number) => number): number[] => {
    const runs: number[] = [];
    const step = Math.max(1, Math.floor(secondaryLen / lineCount));
    for (let s = step >> 1; s < secondaryLen; s += step) {
      let runLen = 1;
      let lineRuns = 0;
      for (let p = 1; p < primaryLen; p++) {
        const i = getIdx(p, s);
        const j = getIdx(p - 1, s);
        if (d[i] === d[j] && d[i + 1] === d[j + 1] && d[i + 2] === d[j + 2]) {
          runLen++;
        } else {
          runs.push(runLen);
          lineRuns++;
          runLen = 1;
        }
      }
      runs.push(runLen);
      // Discard lines that are too flat (probably uniform background).
      if (lineRuns < MIN_RUNS_PER_LINE) {
        // Pop the runs we just added for this line — they would bias K.
        for (let k = 0; k <= lineRuns; k++) runs.pop();
      }
    }
    return runs;
  };

  const hRuns = collectRuns(SCANLINE_COUNT, width, height, (x, y) => (y * width + x) * 4);
  const vRuns = collectRuns(SCANLINE_COUNT, height, width, (y, x) => (y * width + x) * 4);
  const allRuns = hRuns.concat(vRuns);
  if (allRuns.length < 20) return fallback;

  // Find largest K (divisor of both dims) for which MATCH_THRESHOLD of runs
  // are exact multiples of K. Start from the largest plausible factor.
  let bestK = 1;
  for (let k = MAX_K; k >= 2; k--) {
    if (width % k !== 0 || height % k !== 0) continue;
    let matches = 0;
    for (const r of allRuns) {
      if (r % k === 0) matches++;
    }
    if (matches / allRuns.length >= MATCH_THRESHOLD) {
      bestK = k;
      break;
    }
  }

  if (bestK < 2) return fallback;
  return {
    isPixelArt: true,
    logicalWidth: width / bestK,
    logicalHeight: height / bestK,
    upscaleFactor: bestK,
  };
}
