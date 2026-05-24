/**
 * Session persistence for the generator.
 * Stores everything needed to fully restore the user's work after a reload:
 *   - cropped source image (as PNG data URL)
 *   - format / table size / pixel size
 *   - palette + suggestion mode
 *   - manual edits (per-cell overrides)
 *   - UI accordion state
 *
 * Storage: localStorage under a single namespaced key.
 * Writes are debounced (~300ms) to avoid thrashing on every keystroke.
 */

import { PaletteColor } from './pixelArt';
import { SuggestionMode } from './paletteSuggestion';

const STORAGE_KEY = 'pixel-grid-session-v1';
const DEBOUNCE_MS = 300;

export interface PersistedSession {
  imageDataUrl: string | null;
  imgSize: { w: number; h: number };
  /** Grid format chosen by the user (cols × rows, may be non-square). */
  format: { cols: number; rows: number };
  /** Camera viewport rectangle over source image (x, y, w, h in SOURCE pixels). */
  viewport: { x: number; y: number; w: number; h: number };
  pixelSizeMm: number;
  palette: PaletteColor[];
  suggestionMode: SuggestionMode;
  manualEdits: Record<string, string>;
  tagFilter: string | null;
  ui: {
    openFormat: boolean;
    openTable: boolean;
    openPixel: boolean;
    openSuggest: boolean;
    openUsed: boolean;
  };
}


export function loadSession(): Partial<PersistedSession> | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    return JSON.parse(raw) as Partial<PersistedSession>;
  } catch {
    // Corrupted state → ignore (caller falls back to defaults).
    return null;
  }
}

let pending: Partial<PersistedSession> | null = null;
let timer: number | null = null;

export function saveSession(patch: Partial<PersistedSession>) {
  pending = { ...(pending ?? {}), ...patch };
  if (timer != null) return;
  timer = window.setTimeout(() => {
    try {
      const prev = loadSession() ?? {};
      const next = { ...prev, ...(pending ?? {}) };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
    } catch {
      // Storage full or unavailable — silently drop. We never want to break the UI.
    }
    pending = null;
    timer = null;
  }, DEBOUNCE_MS);
}

export function clearSession() {
  try { localStorage.removeItem(STORAGE_KEY); } catch { /* noop */ }
}

/** Convert an HTMLImageElement to a PNG data URL (used to persist the cropped image). */
export function imageToDataUrl(img: HTMLImageElement): string | null {
  try {
    const canvas = document.createElement('canvas');
    canvas.width = img.naturalWidth || img.width;
    canvas.height = img.naturalHeight || img.height;
    const ctx = canvas.getContext('2d');
    if (!ctx) return null;
    ctx.drawImage(img, 0, 0);
    return canvas.toDataURL('image/png');
  } catch {
    return null;
  }
}

/** Rehydrate a data URL into both an HTMLImageElement and ImageData. */
export function dataUrlToImage(
  url: string
): Promise<{ img: HTMLImageElement; data: ImageData } | null> {
  return new Promise((resolve) => {
    const img = new Image();
    img.onload = () => {
      try {
        const canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        if (!ctx) return resolve(null);
        ctx.drawImage(img, 0, 0);
        const data = ctx.getImageData(0, 0, img.width, img.height);
        resolve({ img, data });
      } catch {
        resolve(null);
      }
    };
    img.onerror = () => resolve(null);
    img.src = url;
  });
}
