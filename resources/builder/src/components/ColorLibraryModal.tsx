import { useState, useEffect, useRef } from 'react';
import { Dialog, DialogContent, DialogPortal, DialogOverlay } from '@/components/ui/dialog';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { PaletteColor } from '@/lib/pixelArt';
import { LibraryEntry } from '@/lib/colorLibrary';
import { MappedGrid } from '@/lib/pipeline';
import ColorLibraryManager from './ColorLibraryManager';
import PixelGrid from './PixelGrid';
import { X, Check } from 'lucide-react';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** The current (live) palette being edited. */
  palette: PaletteColor[];
  /** Apply a palette change live (preview updates instantly). */
  onPaletteChange: (palette: PaletteColor[]) => void;
  /** Library row click → add to palette. */
  onPickEntry: (entry: LibraryEntry) => void;
  /** Load a saved palette. */
  onLoadPalette: (colors: { hex: string; name: string; textureUrl?: string }[]) => void;
  /** Live mapped grid for the preview pane. */
  mappedGrid: MappedGrid | null;
  pixelSizeCm: number;
}

/**
 * Fullscreen modal for the color library.
 * - Selecting / deselecting a color updates the preview live.
 * - "Valider" closes and keeps the new palette.
 * - "Annuler" restores the snapshot taken when the modal opened.
 */
export default function ColorLibraryModal({
  open,
  onOpenChange,
  palette,
  onPaletteChange,
  onPickEntry,
  onLoadPalette,
  mappedGrid,
  pixelSizeCm,
}: Props) {
  // Snapshot the palette when the modal opens so "Annuler" can restore it.
  const snapshotRef = useRef<PaletteColor[] | null>(null);

  useEffect(() => {
    if (open) {
      snapshotRef.current = palette;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const handleCancel = () => {
    if (snapshotRef.current) onPaletteChange(snapshotRef.current);
    onOpenChange(false);
  };

  const handleConfirm = () => {
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={(o) => { if (!o) handleCancel(); else onOpenChange(true); }}>
      <DialogPortal>
        <DialogOverlay />
        <DialogPrimitive.Content
          className="fixed inset-0 z-50 flex flex-col bg-background data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        >
          {/* Header */}
          <div className="flex items-center justify-between border-b border-border px-4 sm:px-6 py-3">
            <div>
              <h2 className="font-display text-base font-bold uppercase tracking-widest text-foreground">
                Choisir les couleurs
              </h2>
              <p className="font-mono text-[10px] text-muted-foreground">
                Aperçu en direct — Valider pour confirmer, Annuler pour restaurer
              </p>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={handleCancel}
                className="flex items-center gap-1 rounded bg-secondary px-3 py-1.5 font-mono text-xs text-secondary-foreground hover:bg-accent transition-colors"
              >
                <X size={14} /> Annuler
              </button>
              <button
                onClick={handleConfirm}
                className="flex items-center gap-1 rounded bg-primary px-3 py-1.5 font-mono text-xs font-medium text-primary-foreground hover:opacity-90 transition-opacity"
              >
                <Check size={14} /> Valider
              </button>
            </div>
          </div>

          {/* Body : responsive split */}
          <div className="flex flex-1 min-h-0 flex-col-reverse md:flex-row">
            {/* LEFT (40%) — library */}
            <div className="flex-1 md:flex-none md:basis-[40%] overflow-y-auto border-t md:border-t-0 md:border-r border-border p-4">
              <ColorLibraryManager
                palette={palette}
                onPick={onPickEntry}
                onLoadPalette={onLoadPalette}
              />
            </div>

            {/* RIGHT (60%) — live preview */}
            <div className="flex-1 md:basis-[60%] overflow-y-auto p-4 bg-card/30">
              {mappedGrid ? (
                <PixelGrid
                  mappedGrid={mappedGrid}
                  palette={palette}
                  pixelSizeCm={pixelSizeCm}
                />
              ) : (
                <div className="flex h-full min-h-[300px] items-center justify-center rounded border border-dashed border-border">
                  <p className="font-mono text-xs text-muted-foreground text-center px-4">
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
