import { useRef, useEffect, useCallback, useState }  from 'react';
import { PaletteColor, hexToRgb } from '@/lib/pixelArt';
import { MappedGrid } from '@/lib/pipeline';

interface Props {
  /** Output of the deterministic pipeline. Single source of truth shared by every render mode. */
  mappedGrid: MappedGrid;
  palette: PaletteColor[];
  canvasRef?: React.RefObject<HTMLCanvasElement | null>;
  /** Physical pixel side in cm (e.g. 0.5 = 5mm, 1 = 10mm). */
  pixelSizeCm?: number;
  // ── Manual editing (Stage 7 of the pipeline) ──
  editMode?: boolean;
  onEditPixel?: (row: number, col: number) => void;
}

export default function PixelGrid({
  mappedGrid,
  palette,
  canvasRef: externalRef,
  editMode = false,
  onEditPixel,
}: Props) {
  const { cols, rows, mapped } = mappedGrid;
  const texRef = useRef<HTMLCanvasElement>(null);

  const containerRef = useRef<HTMLDivElement>(null);
  const [zoom, setZoom] = useState(1);
  const [pan, setPan] = useState({ x: 0, y: 0 });
  const [isPanning, setIsPanning] = useState(false);
  const panStart = useRef({ x: 0, y: 0, panX: 0, panY: 0 });
  const [fitScale, setFitScale] = useState(1);

  useEffect(() => {
    if (!externalRef) return;
    const target = texRef.current;
    if (target && externalRef.current !== target) {
      (externalRef as React.MutableRefObject<HTMLCanvasElement | null>).current = target;
    }
  }, [externalRef]);

  // Deterministic pseudo-random in [0,1) seeded by (row, col, salt)
  const cellRand = (row: number, col: number, salt: number) => {
    let h = (row * 73856093) ^ (col * 19349663) ^ (salt * 83492791);
    h = Math.imul(h ^ (h >>> 13), 1274126177);
    return ((h ^ (h >>> 16)) >>> 0) / 4294967296;
  };

  // Load texture images as raw visual assets.
  const [textureImages, setTextureImages] = useState<Map<string, HTMLImageElement>>(new Map());
  useEffect(() => {
    const toLoad = palette.filter(c => c.textureUrl);
    if (toLoad.length === 0) { setTextureImages(new Map()); return; }
    const map = new Map<string, HTMLImageElement>();
    let loaded = 0;
    const done = () => { loaded++; if (loaded === toLoad.length) setTextureImages(new Map(map)); };
    for (const c of toLoad) {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => { map.set(c.hex, img); done(); };
      img.onerror = done;
      img.src = c.textureUrl!;
    }
  }, [palette]);

  const drawCanvas = useCallback((canvas: HTMLCanvasElement | null) => {
    if (!canvas || mapped.length === 0) return;

    // Higher resolution: larger cells → sharper texture sampling.
    const cellSize = Math.max(32, Math.min(96, Math.floor(2400 / Math.max(cols, rows))));
    const labelHeight = 36;
    const w = cols * cellSize;
    const h = rows * cellSize + labelHeight;

    canvas.width = w;
    canvas.height = h;

    const ctx = canvas.getContext('2d')!;
    ctx.fillStyle = '#1a1a1f';
    ctx.fillRect(0, 0, w, h);

    const fontSize = Math.max(8, Math.min(14, cellSize * 0.45));
    ctx.font = `${fontSize}px "JetBrains Mono", monospace`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    for (let row = 0; row < rows; row++) {
      for (let col = 0; col < cols; col++) {
        const matched = mapped[row]?.[col];
        if (!matched) continue;

        const x = col * cellSize;
        const y = row * cellSize;

        if (textureImages.has(matched.hex)) {
          const texImg = textureImages.get(matched.hex)!;
          const minSide = Math.min(texImg.width, texImg.height);
          // Sample at least the cell size in pixels so the rendered cell is HD,
          // even when textures are smaller — never downsample below cell res.
          const desired = Math.max(cellSize * 2, Math.floor(minSide * 0.45));
          const cropSize = Math.max(16, Math.min(minSide, desired));
          const maxX = Math.max(0, texImg.width - cropSize);
          const maxY = Math.max(0, texImg.height - cropSize);
          const intensityFactor = 0.6;
          const r1 = cellRand(row, col, 1);
          const r2 = cellRand(row, col, 2);
          const cx = maxX / 2;
          const cy = maxY / 2;
          let sx = cx + (r1 - 0.5) * maxX * intensityFactor;
          let sy = cy + (r2 - 0.5) * maxY * intensityFactor;
          sx = Math.max(0, Math.min(maxX, sx));
          sy = Math.max(0, Math.min(maxY, sy));
          const prevSmooth = ctx.imageSmoothingEnabled;
          const prevQuality = ctx.imageSmoothingQuality;
          ctx.imageSmoothingEnabled = true;
          ctx.imageSmoothingQuality = 'high';
          ctx.drawImage(texImg, sx, sy, cropSize, cropSize, x, y, cellSize, cellSize);
          ctx.imageSmoothingEnabled = prevSmooth;
          ctx.imageSmoothingQuality = prevQuality;
          ctx.strokeStyle = 'rgba(0,0,0,0.22)';
          ctx.lineWidth = 1;
          ctx.strokeRect(x + 0.5, y + 0.5, cellSize - 1, cellSize - 1);
        } else {
          // Fallback: raw hex
          ctx.fillStyle = matched.hex;
          ctx.fillRect(x, y, cellSize, cellSize);
          ctx.strokeStyle = 'rgba(0,0,0,0.22)';
          ctx.lineWidth = 1;
          ctx.strokeRect(x + 0.5, y + 0.5, cellSize - 1, cellSize - 1);
        }
      }
    }

    const labelY = h - labelHeight;
    ctx.fillStyle = '#1a1a1f';
    ctx.fillRect(0, labelY, w, labelHeight);
    ctx.fillStyle = '#a0956e';
    ctx.font = '14px "Space Grotesk", sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(`${cols} × ${rows} pixels`, w / 2, labelY + labelHeight / 2);
  }, [mapped, palette, cols, rows, textureImages]);

  useEffect(() => {
    drawCanvas(texRef.current);
  }, [drawCanvas]);

  useEffect(() => {
    const container = containerRef.current;
    const canvas = texRef.current;
    if (!container || !canvas || canvas.width === 0) return;
    const cw = container.clientWidth;
    const ch = container.clientHeight;
    const scale = Math.min(cw / canvas.width, ch / canvas.height, 1);
    setFitScale(scale);
    setZoom(1);
    setPan({ x: 0, y: 0 });
  }, [mapped, cols, rows]);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;
    const preventScroll = (e: WheelEvent) => { e.preventDefault(); };
    container.addEventListener('wheel', preventScroll, { passive: false });
    return () => container.removeEventListener('wheel', preventScroll);
  }, []);

  const handleWheel = useCallback((e: React.WheelEvent) => {
    const delta = e.deltaY > 0 ? 0.9 : 1.1;
    setZoom(z => Math.min(Math.max(z * delta, 0.5), 10));
  }, []);

  /** Convert a pointer event into the (row, col) of the clicked cell, or null if outside. */
  const pointerToCell = useCallback((e: React.PointerEvent): { row: number; col: number } | null => {
    const canvas = texRef.current;
    if (!canvas) return null;
    const rect = canvas.getBoundingClientRect();
    const xInCanvas = ((e.clientX - rect.left) / rect.width) * canvas.width;
    const yInCanvas = ((e.clientY - rect.top) / rect.height) * canvas.height;
    const cellSize = Math.max(32, Math.min(96, Math.floor(2400 / Math.max(cols, rows))));
    const gridHeight = rows * cellSize;
    if (xInCanvas < 0 || yInCanvas < 0 || xInCanvas >= cols * cellSize || yInCanvas >= gridHeight) return null;
    const col = Math.floor(xInCanvas / cellSize);
    const row = Math.floor(yInCanvas / cellSize);
    if (row < 0 || col < 0 || row >= rows || col >= cols) return null;
    return { row, col };
  }, [cols, rows]);

  const [isPainting, setIsPainting] = useState(false);

  const handlePointerDown = useCallback((e: React.PointerEvent) => {
    if (editMode) {
      const cell = pointerToCell(e);
      if (cell && onEditPixel) {
        onEditPixel(cell.row, cell.col);
        setIsPainting(true);
        (e.target as HTMLElement).setPointerCapture(e.pointerId);
      }
      return;
    }
    setIsPanning(true);
    panStart.current = { x: e.clientX, y: e.clientY, panX: pan.x, panY: pan.y };
    (e.target as HTMLElement).setPointerCapture(e.pointerId);
  }, [editMode, pan, pointerToCell, onEditPixel]);

  const handlePointerMove = useCallback((e: React.PointerEvent) => {
    if (editMode) {
      if (!isPainting) return;
      const cell = pointerToCell(e);
      if (cell && onEditPixel) onEditPixel(cell.row, cell.col);
      return;
    }
    if (!isPanning) return;
    const dx = e.clientX - panStart.current.x;
    const dy = e.clientY - panStart.current.y;
    setPan({ x: panStart.current.panX + dx, y: panStart.current.panY + dy });
  }, [editMode, isPainting, isPanning, pointerToCell, onEditPixel]);

  const handlePointerUp = useCallback(() => {
    setIsPanning(false);
    setIsPainting(false);
  }, []);
  const handleReset = useCallback(() => { setZoom(1); setPan({ x: 0, y: 0 }); }, []);

  const effectiveScale = fitScale * zoom;

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2 font-mono text-xs text-muted-foreground">
        <button onClick={handleReset} className="rounded bg-secondary px-2 py-1 hover:bg-accent transition-colors">Recadrer</button>
        <span className="ml-auto text-[10px] hidden sm:inline">
          {editMode ? 'Clic = peindre · Glisser = peindre en continu' : 'Molette = zoom · Clic-glisser = déplacer'}
        </span>
      </div>

      <div>
        <div
          ref={containerRef}
          className="relative overflow-hidden rounded border border-border bg-card touch-none"
          style={{
            height: 'min(60vh, 600px)',
            cursor: editMode ? 'crosshair' : (isPanning ? 'grabbing' : 'grab'),
          }}
          onWheel={handleWheel}
          onPointerDown={handlePointerDown}
          onPointerMove={handlePointerMove}
          onPointerUp={handlePointerUp}
          onPointerCancel={handlePointerUp}
        >
          <div
            className="absolute inset-0 flex items-center justify-center"
            style={{
              transform: `translate(${pan.x}px, ${pan.y}px) scale(${effectiveScale})`,
              transformOrigin: 'center center',
              willChange: 'transform',
            }}
          >
            <canvas ref={texRef} className="block" style={{ imageRendering: 'pixelated' }} />
          </div>
        </div>
      </div>
    </div>
  );
}
