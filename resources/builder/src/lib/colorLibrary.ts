export interface LibraryEntry {
  id: string;
  name: string;
  hex: string;
  textureUrl?: string;
  tags: string[];
  gridCode?: string;
  fromCloud?: boolean;
}

export interface SavedPalette {
  id: string;
  name: string;
  entryIds: string[];
  colors: { hex: string; name: string; textureUrl?: string }[];
}

const STORAGE_KEY = 'pixel-grid-color-library';

// Static library — sourced from textures-export.csv, sorted by sort_order.
const STATIC_LIBRARY: LibraryEntry[] = [
  { id: 'chene',                           name: 'Chêne',                     hex: '#d4ac7b', tags: ['Bois français',  'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/chene-1779370379611.jpg',                          gridCode: 'Z',  fromCloud: true },
  { id: 'amarante',                         name: 'Amarante',                  hex: '#6B1839', tags: ['Bois exotiques', 'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Amarante.jpg',                                         gridCode: 'A',  fromCloud: true },
  { id: 'amarello',                         name: 'Amarello',                  hex: '#C8A84E', tags: ['Bois exotiques', 'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Amarello.jpg',                                         gridCode: 'B',  fromCloud: true },
  { id: 'charme_jaune',                     name: 'Charme Jaune',              hex: '#D4B85A', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Charme_Jaune.jpg',                                      gridCode: 'C',  fromCloud: true },
  { id: 'charme_rouge',                     name: 'Charme Rouge',              hex: '#8B3A3A', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Charme_Rouge.jpg',                                      gridCode: 'D',  fromCloud: true },
  { id: 'charme_vert',                      name: 'Charme Vert',               hex: '#4A6B4A', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Charme_Vert.jpg',                                       gridCode: 'E',  fromCloud: true },
  { id: 'sycomore-bleu-lapis-lazuli-lr3wk', name: 'Sycomore Bleu Lapis Lazuli',hex: '#42738D', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/sycomore-bleu-lapis-lazuli-1779346215444.jpg',          gridCode: 'F',  fromCloud: true },
  { id: 'chene_fume',                       name: 'Chêne Fumé',                hex: '#5C4033', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/chene-fume-1779370392048.jpg',                          gridCode: 'G',  fromCloud: true },
  { id: 'citronnier',                       name: 'Citronnier',                hex: '#E8D44D', tags: ['Bois exotiques', 'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Citronnier.jpg',                                        gridCode: 'H',  fromCloud: true },
  { id: 'sycomore',                         name: 'Sycomore',                  hex: '#E8DCC8', tags: ['Bois français',  'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/sycomore-1779370411650.jpg',                            gridCode: 'I',  fromCloud: true },
  { id: 'frene_bleu',                       name: 'Frêne Bleu',                hex: '#4A6B8A', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Frene_Bleu.jpg',                                        gridCode: 'J',  fromCloud: true },
  { id: 'frene_jaune',                      name: 'Frêne Jaune',               hex: '#D4C25A', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Frene_Jaune.jpg',                                       gridCode: 'K',  fromCloud: true },
  { id: 'frene_orange',                     name: 'Frêne Orange',              hex: '#D4813A', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Frene_Orange.jpg',                                      gridCode: 'L',  fromCloud: true },
  { id: 'frene_rouge',                      name: 'Frêne Rouge',               hex: '#9B3A3A', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Frene_Rouge.jpg',                                       gridCode: 'M',  fromCloud: true },
  { id: 'frene_vert',                       name: 'Frêne Vert',                hex: '#5A8B5A', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Frene_Vert.jpg',                                        gridCode: 'N',  fromCloud: true },
  { id: 'hetre',                            name: 'Hêtre',                     hex: '#D4A76A', tags: ['Bois français',  'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/hetre-1779370427281.jpg',                              gridCode: 'O',  fromCloud: true },
  { id: 'merisier',                         name: 'Merisier',                  hex: '#B5651D', tags: ['Bois français',  'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/merisier-1779370436562.jpg',                           gridCode: 'P',  fromCloud: true },
  { id: 'movingui',                         name: 'Movingui',                  hex: '#C8B438', tags: ['Bois exotiques', 'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Movingui.jpg',                                         gridCode: 'Q',  fromCloud: true },
  { id: 'noyer',                            name: 'Noyer',                     hex: '#5C3A1E', tags: ['Bois français',  'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/noyer-1779370445200.jpg',                              gridCode: 'R',  fromCloud: true },
  { id: 'orme',                             name: 'Orme',                      hex: '#8B7355', tags: ['Bois français',  'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/orme-1779370452939.jpg',                              gridCode: 'S',  fromCloud: true },
  { id: 'padouk',                           name: 'Padouk',                    hex: '#A63A2D', tags: ['Bois exotiques', 'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Padouk.jpg',                                          gridCode: 'T',  fromCloud: true },
  { id: 'poirier_alisier',                  name: 'Poirier Alisier',           hex: '#D4A088', tags: ['Bois français',  'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/poirier-alisier-1779370462000.jpg',                    gridCode: 'U',  fromCloud: true },
  { id: 'sycomore_bleu',                    name: 'Sycomore Bleu',             hex: '#6A8BA0', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Sycomore_Bleu.jpg',                                     gridCode: 'V',  fromCloud: true },
  { id: 'sycomore_rose',                    name: 'Sycomore Rose',             hex: '#C8A0A0', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Sycomore_Rose.jpg',                                     gridCode: 'W',  fromCloud: true },
  { id: 'tulipier_noir',                    name: 'Tulipier Noir',             hex: '#2A2A2A', tags: ['Bois exotique',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Tulipier_Noir.jpg',                                     gridCode: 'X',  fromCloud: true },
  { id: 'tulipier_violet',                  name: 'Tulipier Violet',           hex: '#5A2D5A', tags: ['Bois exotique',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/Tulipier_Violet.jpg',                                   gridCode: 'Y',  fromCloud: true },
  { id: 'sycomore-bleu-adriatique-5di0j',   name: 'Sycomore Bleu Adriatique',  hex: '#00B0D4', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/sycomore-bleu-adriatique-1779346281708.jpg',           gridCode: 'AA', fromCloud: true },
  { id: 'sycomore-bleu-marine-dm0i1',       name: 'Sycomore Bleu Marine',      hex: '#324265', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/sycomore-bleu-marine-1779346396615.jpg',              gridCode: 'AB', fromCloud: true },
  { id: 'sycomore-vert-trefle-u50xv',       name: 'Sycomore Vert Trèfle',      hex: '#67A072', tags: ['Bois français',  'Couleur teintée'],   textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/sycomore-vert-trefle-1779347734730.jpg',              gridCode: 'AC', fromCloud: true },
  { id: 'frene-c93qd',                      name: 'Frêne',                     hex: '#E3CFAC', tags: ['Bois français',  'Couleur naturelle'], textureUrl: 'https://apunwmswkqwrcwddfsff.supabase.co/storage/v1/object/public/wood-textures/frene-1779370472970.jpg',                              gridCode: 'AD', fromCloud: true },
];

export const TAG_CATEGORIES: { label: string; tags: string[] }[] = [
  { label: 'Origine', tags: ['Bois français', 'Bois exotique'] },
  { label: 'Couleur', tags: ['Couleur naturelle', 'Couleur teintée'] },
];

export function getTagsByCategory(): { label: string; tags: string[] }[] {
  const lib = loadLibrary();
  const allTags = new Set<string>();
  for (const e of lib) for (const t of e.tags) allTags.add(t);
  return TAG_CATEGORIES
    .map(cat => ({ label: cat.label, tags: cat.tags.filter(t => allTags.has(t)) }))
    .filter(cat => cat.tags.length > 0);
}

const PALETTES_KEY = 'pixel-grid-saved-palettes';

const TAG_RENAMES: Record<string, string> = {
  'Français': 'Bois français',
  'Exotique': 'Bois exotique',
  'Bois exotiques': 'Bois exotique',
  'Americain': 'Bois exotique',
  'Bois américains': 'Bois exotique',
  'Bois américain': 'Bois exotique',
  'Naturelle': 'Couleur naturelle',
  'Teintée': 'Couleur teintée',
  'Traitée': 'Couleur teintée',
};

function migrateTags(tags: string[]): string[] {
  return tags.map(t => TAG_RENAMES[t] ?? t);
}

export function loadLibrary(): LibraryEntry[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    const entries: LibraryEntry[] = raw ? JSON.parse(raw) : [];
    const migrated = entries.map((e: any) => ({
      ...e,
      tags: migrateTags(e.tags || []),
    }));
    if (migrated.length > 0) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(migrated));
      return migrated;
    }
  } catch { /* ignore */ }
  return STATIC_LIBRARY;
}

export function saveLibrary(entries: LibraryEntry[]) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(entries));
}

export function addToLibrary(entry: Omit<LibraryEntry, 'id'>): LibraryEntry {
  const lib = loadLibrary();
  const newEntry: LibraryEntry = { ...entry, id: crypto.randomUUID() };
  lib.push(newEntry);
  saveLibrary(lib);
  return newEntry;
}

export function updateLibraryEntry(id: string, updates: Partial<Omit<LibraryEntry, 'id'>>) {
  const lib = loadLibrary();
  const idx = lib.findIndex(e => e.id === id);
  if (idx >= 0) {
    lib[idx] = { ...lib[idx], ...updates };
    saveLibrary(lib);
  }
}

export function removeFromLibrary(id: string) {
  const lib = loadLibrary().filter(e => e.id !== id);
  saveLibrary(lib);
}

export function getAllTags(): string[] {
  const lib = loadLibrary();
  const tags = new Set<string>();
  for (const e of lib) {
    for (const t of e.tags) tags.add(t);
  }
  return [...tags].sort();
}

// Palette save/load
export function loadPalettes(): SavedPalette[] {
  try {
    const raw = localStorage.getItem(PALETTES_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

export function savePalettes(palettes: SavedPalette[]) {
  localStorage.setItem(PALETTES_KEY, JSON.stringify(palettes));
}

export function addPalette(name: string, colors: { hex: string; name: string; textureUrl?: string }[]): SavedPalette {
  const palettes = loadPalettes();
  const palette: SavedPalette = {
    id: crypto.randomUUID(),
    name,
    entryIds: [],
    colors,
  };
  palettes.push(palette);
  savePalettes(palettes);
  return palette;
}

export function removePalette(id: string) {
  savePalettes(loadPalettes().filter(p => p.id !== id));
}

/** Convert a blob/object URL to a base64 data URL for persistence */
export function blobUrlToDataUrl(url: string): Promise<string> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
      const canvas = document.createElement('canvas');
      canvas.width = img.width;
      canvas.height = img.height;
      const ctx = canvas.getContext('2d')!;
      ctx.drawImage(img, 0, 0);
      resolve(canvas.toDataURL('image/png'));
    };
    img.onerror = reject;
    img.src = url;
  });
}