/**
 * Persistent symbol mapping: each color (hex) always gets the same symbol.
 * Stored in localStorage so mappings survive across sessions.
 */

const STORAGE_KEY = 'pixel-grid-symbol-map';
const SYMBOLS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

function loadMap(): Record<string, string> {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch {
    return {};
  }
}

function saveMap(map: Record<string, string>) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(map));
}

/**
 * Get a deterministic, persistent symbol for a given hex color.
 * If the hex was seen before, return the same symbol.
 * Otherwise assign the next available symbol and persist it.
 */
export function getSymbolForHex(hex: string, usedSymbols: Set<string>): string {
  const map = loadMap();
  const normalizedHex = hex.toLowerCase();

  // Already mapped → return it (if not conflicting with current palette)
  if (map[normalizedHex] && !usedSymbols.has(map[normalizedHex])) {
    return map[normalizedHex];
  }

  // Find next available symbol
  for (const s of SYMBOLS) {
    if (!usedSymbols.has(s)) {
      map[normalizedHex] = s;
      saveMap(map);
      return s;
    }
  }

  return '?';
}

/**
 * Assign symbols to a full palette at once, preserving any existing mappings.
 */
export function assignSymbolsToPalette(
  hexValues: string[]
): string[] {
  const map = loadMap();
  const result: string[] = [];
  const usedInThisBatch = new Set<string>();

  // First pass: honour existing persistent mappings
  for (const hex of hexValues) {
    const key = hex.toLowerCase();
    if (map[key] && !usedInThisBatch.has(map[key])) {
      result.push(map[key]);
      usedInThisBatch.add(map[key]);
    } else {
      result.push(''); // placeholder
    }
  }

  // Second pass: fill blanks with next available symbols
  for (let i = 0; i < hexValues.length; i++) {
    if (result[i]) continue;
    const key = hexValues[i].toLowerCase();
    for (const s of SYMBOLS) {
      if (!usedInThisBatch.has(s)) {
        result[i] = s;
        usedInThisBatch.add(s);
        map[key] = s;
        break;
      }
    }
    if (!result[i]) result[i] = '?';
  }

  saveMap(map);
  return result;
}
