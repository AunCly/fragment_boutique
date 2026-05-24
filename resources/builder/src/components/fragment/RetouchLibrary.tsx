import { useMemo } from 'react';
import { loadLibrary, LibraryEntry } from '@/lib/colorLibrary';

interface Props {
  selectedHex: string | null;
  onSelect: (entry: LibraryEntry) => void;
  /** Bump to force re-read of library (when cloud seeding completes). */
  libraryTick: number;
}

/**
 * Inline tag-grouped wood library used inside the "Retouche manuelle" panel.
 * Display order:
 *  1. "Bois français · couleur naturelle"
 *  2. "Bois exotique · couleur naturelle"
 *  3. "Bois teintés"
 */
export default function RetouchLibrary({ selectedHex, onSelect, libraryTick }: Props) {
  const groups = useMemo(() => {
    const lib = loadLibrary();
    const frenchNatural: LibraryEntry[] = [];
    const exoticNatural: LibraryEntry[] = [];
    const teintes: LibraryEntry[] = [];
    for (const e of lib) {
      const isFrench = e.tags.includes('Bois français');
      const isTeinte = e.tags.includes('Couleur teintée');
      if (isTeinte) { teintes.push(e); continue; }
      if (isFrench) frenchNatural.push(e);
      else exoticNatural.push(e);
    }
    const ordered: { label: string; entries: LibraryEntry[] }[] = [];
    if (frenchNatural.length) ordered.push({ label: 'Bois français · couleur naturelle', entries: frenchNatural });
    if (exoticNatural.length) ordered.push({ label: 'Bois exotique · couleur naturelle', entries: exoticNatural });
    if (teintes.length) ordered.push({ label: 'Bois teintés', entries: teintes });
    return ordered;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [libraryTick]);

  const renderEntry = (e: LibraryEntry) => {
    const active = selectedHex?.toLowerCase() === e.hex.toLowerCase();
    return (
      <button
        key={e.id}
        onClick={() => onSelect(e)}
        title={e.name}
        className={`group flex flex-col items-center gap-1.5 rounded-lg p-1.5 transition-colors ${
          active ? 'bg-accent/15 ring-2 ring-accent' : 'hover:bg-muted/60'
        }`}
      >
        <span className="block h-12 w-12 overflow-hidden rounded-md border border-border">
          {e.textureUrl ? (
            <img src={e.textureUrl} alt="" className="h-full w-full object-cover" />
          ) : (
            <span className="block h-full w-full" style={{ backgroundColor: e.hex }} />
          )}
        </span>
        <span className="block w-full font-sans-soft text-[10px] leading-tight text-foreground text-center break-words">
          {e.name}
        </span>
      </button>
    );
  };

  return (
    <div className="space-y-5">
      {groups.map(g => (
        <div key={g.label}>
          <div className="mb-2 font-sans-soft text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
            {g.label}
          </div>
          <div className="grid grid-cols-[repeat(auto-fill,minmax(72px,1fr))] gap-1">
            {g.entries.map(renderEntry)}
          </div>
        </div>
      ))}
    </div>
  );
}
