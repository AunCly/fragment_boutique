import { PaletteColor } from '@/lib/pixelArt';
import { Trash2 } from 'lucide-react';

interface Props {
  palette: PaletteColor[];
  onChange: (palette: PaletteColor[]) => void;
}

export default function PaletteEditor({ palette, onChange }: Props) {
  const removeColor = (idx: number) => {
    onChange(palette.filter((_, i) => i !== idx));
  };

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="font-display text-sm font-semibold uppercase tracking-widest text-muted-foreground">
          Palette active
        </h2>
        <span className="font-mono text-[10px] text-muted-foreground">{palette.length} couleurs</span>
      </div>

      {palette.length === 0 && (
        <p className="font-mono text-xs text-muted-foreground">Aucune couleur. Ajoutez depuis la bibliothèque ci-dessous.</p>
      )}

      <div className="grid grid-cols-2 gap-2">
        {palette.map((color, idx) => (
          <div
            key={`${color.hex}-${color.symbol}`}
            className="group relative flex items-center gap-2 rounded bg-secondary p-2 transition-colors hover:bg-muted"
          >
            <div className="relative h-8 w-8 rounded-sm border border-border flex-shrink-0 overflow-hidden">
              {color.textureUrl ? (
                <img src={color.textureUrl} alt="" className="h-full w-full object-cover" />
              ) : (
                <div className="h-full w-full" style={{ backgroundColor: color.hex }} />
              )}
            </div>
            <div className="flex-1 min-w-0">
              {color.name && (
                <span className="font-mono text-[10px] text-foreground block truncate font-semibold">{color.name}</span>
              )}
              <span className="font-mono text-[10px] text-muted-foreground block">{color.hex}</span>
              <span className="font-mono text-[10px] text-muted-foreground">Sym: {color.symbol}</span>
            </div>
            <button
              onClick={() => removeColor(idx)}
              className="opacity-0 group-hover:opacity-100 text-destructive transition-opacity"
            >
              <Trash2 size={14} />
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}
