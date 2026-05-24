import { ColorAnalysis } from '@/lib/pixelArt';

interface Props {
  analysis: ColorAnalysis;
  currentPaletteSize: number;
}

export default function ColorAnalysisPanel({ analysis, currentPaletteSize }: Props) {
  if (analysis.distinctColors === 0) return null;

  const topColors = analysis.clusters.slice(0, 12);
  const totalPixels = analysis.clusters.reduce((s, c) => s + c.count, 0);

  return (
    <div className="space-y-2">
      <h2 className="font-display text-sm font-semibold uppercase tracking-widest text-muted-foreground">
        Analyse des couleurs
      </h2>
      <div className="rounded bg-secondary/50 border border-border p-2 space-y-2">
        <p className="font-mono text-xs text-foreground">
          Couleurs détectées : <span className="text-primary font-medium">{analysis.distinctColors}</span>
        </p>
        <p className="font-mono text-xs text-foreground">
          Palette recommandée : <span className="text-primary font-medium">{analysis.recommendedPaletteSize} couleurs</span>
        </p>
        {currentPaletteSize < analysis.recommendedPaletteSize && (
          <p className="font-mono text-[10px] text-amber-500">
            ⚠ Votre palette ({currentPaletteSize}) est plus petite que recommandé ({analysis.recommendedPaletteSize})
          </p>
        )}
        {currentPaletteSize > 0 && currentPaletteSize >= analysis.recommendedPaletteSize && (
          <p className="font-mono text-[10px] text-green-500">
            ✓ Palette adaptée à la complexité de l'image
          </p>
        )}
      </div>

      <div className="flex flex-wrap gap-1">
        {topColors.map((c, i) => (
          <div
            key={i}
            className="group relative h-6 rounded-sm border border-border overflow-hidden"
            style={{
              width: `${Math.max(12, (c.count / totalPixels) * 200)}px`,
              backgroundColor: c.hex,
            }}
            title={`${c.hex} — ${Math.round((c.count / totalPixels) * 100)}%`}
          />
        ))}
      </div>
      <p className="font-mono text-[10px] text-muted-foreground">
        Répartition des {Math.min(12, analysis.clusters.length)} couleurs dominantes
      </p>
    </div>
  );
}
