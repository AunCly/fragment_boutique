import { LibraryEntry, loadLibrary } from './colorLibrary';
import { ColorAnalysis, PaletteColor, hexToRgb, rgbToLab, labDistance } from './pixelArt';
import { assignSymbolsToPalette } from './symbolMapping';

export type SuggestionMode = 'filtered' | 'all' | 'custom';

/** Default tag filter for "filtered" mode (AND logic). */
export const DEFAULT_FILTER_TAGS = ['Couleur naturelle', 'Bois français'];

/** Source library entries for a given suggestion mode. */
export function getLibrarySource(mode: SuggestionMode): LibraryEntry[] {
  const lib = loadLibrary();
  if (mode === 'filtered') {
    const filtered = lib.filter(e => DEFAULT_FILTER_TAGS.every(t => e.tags.includes(t)));
    return filtered.length > 0 ? filtered : lib;
  }
  return lib;
}

/**
 * Suggest a palette by greedily picking the perceptually-closest library
 * entry (CIE76 ΔE in LAB) for each dominant source cluster, frequency-first.
 *
 * Deterministic. No hue-sector enforcement, no eviction, no fallback
 * outside the active source pool — the active filter is the contract.
 */
export function suggestPalette(
  analysis: ColorAnalysis,
  source: LibraryEntry[],
  maxColors = 16,
): PaletteColor[] {
  if (source.length === 0 || analysis.clusters.length === 0) return [];

  const sourceLab = source.map(e => {
    const [r, g, b] = hexToRgb(e.hex);
    return { entry: e, lab: rgbToLab(r, g, b) };
  });

  const clusters = [...analysis.clusters]
    .sort((a, b) => b.count - a.count)
    .map(c => ({ ...c, lab: rgbToLab(c.rgb[0], c.rgb[1], c.rgb[2]) }));

  const pickedIds = new Set<string>();
  const picked: LibraryEntry[] = [];

  for (const c of clusters) {
    if (picked.length >= maxColors) break;
    let best: LibraryEntry | null = null;
    let bestDist = Infinity;
    for (const s of sourceLab) {
      if (pickedIds.has(s.entry.id)) continue;
      const d = labDistance(c.lab, s.lab);
      if (d < bestDist) { bestDist = d; best = s.entry; }
    }
    if (best) { pickedIds.add(best.id); picked.push(best); }
  }

  const symbols = assignSymbolsToPalette(picked.map(e => e.hex));
  return picked.map((e, i) => ({
    hex: e.hex,
    symbol: symbols[i],
    name: e.name,
    textureUrl: e.textureUrl,
    textureAvgColor: e.hex,
  }));
}
