import { useEffect, useRef } from 'react';

interface Viewport { x: number; y: number; w: number; h: number }

interface Props {
  /** ORIGINAL source image data (full resolution, never resampled). */
  sourceImageData: ImageData | null;
  /** Active viewport rectangle in source pixels. */
  viewport: Viewport;
  /** Physical width/height of the artwork in cm. */
  widthCm: number;
  heightCm: number;
  /** Pixel side (mm) to preview. */
  pixelSizeMm: number;
  /** Visual height cap in CSS px. */
  maxHeightPx?: number;
}

/**
 * Live preview of PIXEL DENSITY for a given detail level.
 *
 * Always samples from the ORIGINAL source image + active viewport rect, so
 * the Détaillé card shows true high-resolution detail regardless of which
 * detail level is currently active. (If we sampled from the already-reduced
 * viewportImageData, the finer preview could never reveal more detail than
 * the coarser one — it would just upsample blocks.)
 *
 * Palette mapping is intentionally bypassed: this card answers "how fine
 * will the pixels be?", not "what colors will I get?".
 */
export default function DetailPreview({
  sourceImageData, viewport, widthCm, heightCm, pixelSizeMm, maxHeightPx = 150,
}: Props) {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas || !sourceImageData) return;
    if (viewport.w <= 0 || viewport.h <= 0) return;

    const cols = Math.max(1, Math.round((widthCm * 10) / pixelSizeMm));
    const rows = Math.max(1, Math.round((heightCm * 10) / pixelSizeMm));

    const srcW = sourceImageData.width, srcH = sourceImageData.height;
    const src = sourceImageData.data;

    const ox = Math.min(Math.max(0, viewport.x), srcW - 1);
    const oy = Math.min(Math.max(0, viewport.y), srcH - 1);
    const vw = Math.min(viewport.w, srcW - ox);
    const vh = Math.min(viewport.h, srcH - oy);

    const cellPx = 6;
    canvas.width = cols * cellPx;
    canvas.height = rows * cellPx;
    const ctx = canvas.getContext('2d')!;
    ctx.imageSmoothingEnabled = false;

    for (let gy = 0; gy < rows; gy++) {
      const y0 = oy + (gy * vh) / rows;
      const y1 = oy + ((gy + 1) * vh) / rows;
      const ys = Math.floor(y0), ye = Math.max(ys + 1, Math.ceil(y1));
      for (let gx = 0; gx < cols; gx++) {
        const x0 = ox + (gx * vw) / cols;
        const x1 = ox + ((gx + 1) * vw) / cols;
        const xs = Math.floor(x0), xe = Math.max(xs + 1, Math.ceil(x1));
        let r = 0, g = 0, b = 0, n = 0;
        for (let y = ys; y < ye && y < srcH; y++) {
          for (let x = xs; x < xe && x < srcW; x++) {
            const i = (y * srcW + x) * 4;
            r += src[i]; g += src[i + 1]; b += src[i + 2]; n++;
          }
        }
        if (n > 0) {
          ctx.fillStyle = `rgb(${(r / n) | 0},${(g / n) | 0},${(b / n) | 0})`;
          ctx.fillRect(gx * cellPx, gy * cellPx, cellPx, cellPx);
        }
      }
    }
  }, [sourceImageData, viewport.x, viewport.y, viewport.w, viewport.h, widthCm, heightCm, pixelSizeMm]);

  const ratio = widthCm / heightCm;
  const cssHeight = maxHeightPx;
  const cssWidth = cssHeight * ratio;

  return (
    <canvas
      ref={canvasRef}
      className="block rounded-sm soft-shadow"
      style={{
        imageRendering: 'pixelated',
        height: `${cssHeight}px`,
        width: `${cssWidth}px`,
        maxWidth: '100%',
      }}
    />
  );
}
