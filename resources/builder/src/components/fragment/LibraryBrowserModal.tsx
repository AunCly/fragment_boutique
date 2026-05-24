import { useEffect, useMemo, useRef, useState } from 'react';
import { Dialog, DialogPortal, DialogOverlay } from '@/components/ui/dialog';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X, Check, Plus } from 'lucide-react';
import { PaletteColor } from '@/lib/pixelArt';
import { LibraryEntry, loadLibrary } from '@/lib/colorLibrary';
import { MappedGrid } from '@/lib/pipeline';
import PixelGrid from '@/components/PixelGrid';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  palette: PaletteColor[];
  /** Live, instant palette mutation (preview reflects immediately). */
  onPaletteChange: (palette: PaletteColor[]) => void;
  /** Add an essence to the palette (handles symbol assignment). */
  onAddEntry: (entry: LibraryEntry) => void;
  mappedGrid: MappedGrid | null;
  pixelSizeCm: number;
  libraryTick: number;
}

/**
 * Premium library browser.
 * Left (60%): live Fragment preview.
 * Right (40%): woods grouped by tag — toggle add/remove with one click.
 * Validate keeps changes, Annuler restores the palette snapshot.
 */
export default function LibraryBrowserModal({
  open, onOpenChange, palette, onPaletteChange, onAddEntry,
  mappedGrid, pixelSizeCm, libraryTick,
}: Props) {
  const snapshotRef = useRef<PaletteColor[] | null>(null);
  useEffect(() => { if (open) snapshotRef.current = palette;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const groups = useMemo(() => {
    const lib = loadLibrary();
    const french: LibraryEntry[] = [];
    const exotic: LibraryEntry[] = [];
    const tinted: LibraryEntry[] = [];
    for (const e of lib) {
      if (e.tags.includes('Couleur teintée')) { tinted.push(e); continue; }
      if (e.tags.includes('Bois français')) french.push(e);
      else exotic.push(e);
    }
    const out: { label: string; entries: LibraryEntry[] }[] = [];
    if (french.length) out.push({ label: 'Bois français · couleur naturelle', entries: french });
    if (exotic.length) out.push({ label: 'Bois exotique · couleur naturelle', entries: exotic });
    if (tinted.length) out.push({ label: 'Bois teintés', entries: tinted });
    return out;
  }, [libraryTick, open]);

  const inPalette = (hex: string) =>
    palette.some(c => c.hex.toLowerCase() === hex.toLowerCase());

  const handleToggle = (e: LibraryEntry) => {
    if (inPalette(e.hex)) {
      if (palette.length <= 1) return; // never empty
      onPaletteChange(palette.filter(c => c.hex.toLowerCase() !== e.hex.toLowerCase()));
    } else {
      onAddEntry(e);
    }
  };

  const handleCancel = () => {
    if (snapshotRef.current) onPaletteChange(snapshotRef.current);
    onOpenChange(false);
  };
  const handleConfirm = () => onOpenChange(false);

  return (
    <Dialog open={open} onOpenChange={(o) => { if (!o) handleCancel(); else onOpenChange(true); }}>
      <DialogPortal>
        <DialogOverlay />
        <DialogPrimitive.Content className="fragment-theme fixed inset-0 z-50 flex flex-col bg-background data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0">
          {/* Header */}
          <div className="flex items-center justify-between border-b border-border px-5 sm:px-8 py-4">
            <div>
              <h2 className="font-serif text-2xl text-foreground">Bibliothèque des essences</h2>
              <p className="mt-1 font-sans-soft text-sm text-muted-foreground">
                Ajoutez ou retirez des essences — l'aperçu se met à jour en direct.
              </p>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={handleCancel}
                className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-4 py-2 font-sans-soft text-sm text-foreground hover:bg-muted"
              >
                <X size={14} /> Annuler
              </button>
              <button
                onClick={handleConfirm}
                className="inline-flex items-center gap-1.5 rounded-full bg-primary px-5 py-2 font-sans-soft text-sm font-medium text-primary-foreground hover:opacity-95 soft-shadow"
              >
                <Check size={14} /> Valider
              </button>
            </div>
          </div>

          {/* Body */}
          <div className="flex flex-1 min-h-0 flex-col-reverse md:flex-row">
            {/* Library */}
            <div className="flex-1 md:flex-none md:basis-[42%] overflow-y-auto border-t md:border-t-0 md:border-r border-border p-5 sm:p-7 space-y-7">
              {groups.map(g => (
                <div key={g.label}>
                  <div className="mb-3 font-sans-soft text-[11px] uppercase tracking-[0.2em] text-muted-foreground">
                    {g.label}
                  </div>
                  <div className="grid grid-cols-[repeat(auto-fill,minmax(84px,1fr))] gap-2">
                    {g.entries.map(e => {
                      const active = inPalette(e.hex);
                      return (
                        <button
                          key={e.id}
                          onClick={() => handleToggle(e)}
                          title={active ? 'Retirer de la composition' : 'Ajouter à la composition'}
                          className={`group relative flex flex-col items-center gap-1.5 rounded-xl border p-2 transition-all ${
                            active
                              ? 'border-accent bg-accent/10'
                              : 'border-border bg-card hover:border-accent/60 hover:bg-muted/50'
                          }`}
                        >
                          <span className="block h-14 w-14 overflow-hidden rounded-md border border-border">
                            {e.textureUrl ? (
                              <img src={e.textureUrl} alt="" className="h-full w-full object-cover" />
                            ) : (
                              <span className="block h-full w-full" style={{ backgroundColor: e.hex }} />
                            )}
                          </span>
                          <span className="block w-full font-sans-soft text-[10.5px] leading-tight text-foreground text-center break-words">
                            {e.name}
                          </span>
                          <span className={`absolute right-1.5 top-1.5 inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] ${
                            active ? 'bg-accent text-accent-foreground' : 'bg-background/80 text-muted-foreground opacity-0 group-hover:opacity-100'
                          }`}>
                            {active ? <Check size={11} /> : <Plus size={11} />}
                          </span>
                        </button>
                      );
                    })}
                  </div>
                </div>
              ))}
              {groups.length === 0 && (
                <p className="font-sans-soft text-sm text-muted-foreground">Bibliothèque vide.</p>
              )}
            </div>

            {/* Live preview */}
            <div className="flex-1 md:basis-[58%] overflow-y-auto p-5 sm:p-7 bg-card/30">
              {mappedGrid ? (
                <PixelGrid
                  mappedGrid={mappedGrid}
                  palette={palette}
                  pixelSizeCm={pixelSizeCm}
                />
              ) : (
                <div className="flex h-full min-h-[300px] items-center justify-center rounded-xl border border-dashed border-border">
                  <p className="font-sans-soft text-sm text-muted-foreground text-center px-4">
                    Importez une image pour voir l'aperçu
                  </p>
                </div>
              )}
            </div>
          </div>
        </DialogPrimitive.Content>
      </DialogPortal>
    </Dialog>
  );
}
