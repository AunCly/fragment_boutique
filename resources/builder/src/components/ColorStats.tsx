import { PaletteColor } from '@/lib/pixelArt';
import { MappedGrid } from '@/lib/pipeline';

interface Props {
  /** Output of the deterministic pipeline — already includes manual edits. */
  mappedGrid: MappedGrid;
  palette: PaletteColor[];
}

export default function ColorStats({ mappedGrid, palette }: Props) {
  const { mapped, cols, rows } = mappedGrid;
  if (mapped.length === 0 || palette.length === 0) return null;

  // Count usage directly from the pipeline output → reflects manual edits live.
  const counts = new Map<string, { color: PaletteColor; count: number }>();
  for (let r = 0; r < mapped.length; r++) {
    for (let c = 0; c < mapped[r].length; c++) {
      const matched = mapped[r][c];
      const existing = counts.get(matched.hex);
      if (existing) {
        existing.count++;
      } else {
        counts.set(matched.hex, { color: matched, count: 1 });
      }
    }
  }

  const sortedColors = [...counts.values()].sort((a, b) => b.count - a.count);
  const total = cols * rows;

  return (
    <div className="space-y-3">
      <h2 className="font-display text-sm font-semibold uppercase tracking-widest text-muted-foreground">
        Couleurs utilisées ({sortedColors.length})
      </h2>
      <div className="space-y-1">
        {sortedColors.map(({ color, count }) => (
          <div key={color.hex} className="flex items-center gap-2 rounded bg-secondary px-2 py-1.5">
            <div className="h-5 w-5 rounded-sm border border-border flex-shrink-0 overflow-hidden">
              {color.textureUrl ? (
                <img src={color.textureUrl} alt="" className="h-full w-full object-cover" />
              ) : (
                <div className="h-full w-full" style={{ backgroundColor: color.hex }} />
              )}
            </div>
            <span className="font-mono text-xs font-bold text-foreground w-5 text-center">{color.symbol}</span>
            <div className="flex-1 min-w-0">
              {color.name && (
                <span className="font-mono text-[10px] text-foreground block truncate font-medium">{color.name}</span>
              )}
              <span className="font-mono text-[10px] text-muted-foreground">{color.hex}</span>
            </div>
            <span className="font-mono text-xs text-primary font-medium">{count}</span>
            <span className="font-mono text-[10px] text-muted-foreground">
              {Math.round((count / total) * 100)}%
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}
