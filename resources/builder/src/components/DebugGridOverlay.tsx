import { useRef, useEffect } from 'react';

interface Props {
  image: HTMLImageElement;
  verticalLines: number[];
  horizontalLines: number[];
}

export default function DebugGridOverlay({ image, verticalLines, horizontalLines }: Props) {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;

    canvas.width = image.width;
    canvas.height = image.height;
    const ctx = canvas.getContext('2d')!;

    // Draw original image
    ctx.drawImage(image, 0, 0);

    // Draw detected vertical grid lines (red)
    ctx.strokeStyle = 'rgba(255, 50, 50, 0.8)';
    ctx.lineWidth = 1;
    for (const x of verticalLines) {
      ctx.beginPath();
      ctx.moveTo(x + 0.5, 0);
      ctx.lineTo(x + 0.5, image.height);
      ctx.stroke();
    }

    // Draw detected horizontal grid lines (cyan)
    ctx.strokeStyle = 'rgba(50, 200, 255, 0.8)';
    for (const y of horizontalLines) {
      ctx.beginPath();
      ctx.moveTo(0, y + 0.5);
      ctx.lineTo(image.width, y + 0.5);
      ctx.stroke();
    }
  }, [image, verticalLines, horizontalLines]);

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-3">
        <h2 className="font-display text-sm font-semibold uppercase tracking-widest text-muted-foreground">
          Debug — Grille détectée
        </h2>
        <div className="flex items-center gap-2 font-mono text-xs">
          <span className="inline-block h-2 w-4 rounded-sm bg-red-500" /> {verticalLines.length} V
          <span className="inline-block h-2 w-4 rounded-sm bg-cyan-400" /> {horizontalLines.length} H
        </div>
      </div>
      <canvas
        ref={canvasRef}
        className="w-full rounded border border-border"
        style={{ imageRendering: 'pixelated' }}
      />
    </div>
  );
}
