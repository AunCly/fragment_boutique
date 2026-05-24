import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * MOSAIC VIEWPORT — pan + edge-resize rectangle over a static source image.
 *
 * The viewport is a rectangle of (w, h) source pixels at (x, y). Its aspect
 * ratio is locked to the chosen grid format (cols / rows). Resize handles
 * preserve that ratio. No zoom buttons, no slider — interaction is purely
 * via dragging the body (pan) or the 8 handles (resize).
 *
 * - Source image is immutable (never scaled/cropped).
 * - The pipeline downsamples the viewport region to (cols, rows).
 */

export interface Format {
  cols: number;
  rows: number;
}

export interface Viewport {
  /** top-left in SOURCE pixels */
  x: number;
  y: number;
  /** width in SOURCE pixels */
  w: number;
  /** height in SOURCE pixels */
  h: number;
}

// Dimensions in CENTIMETERS (physical table size). The grid resolution is
// derived from these dimensions and the pixel size (mm) chosen in step 3.
export const FORMAT_GROUPS: { label: string; options: Format[] }[] = [
  { label: 'Carré',    options: [{ cols: 16, rows: 16 }, { cols: 32, rows: 32 }] },
  { label: 'Paysage',  options: [{ cols: 32, rows: 16 }, { cols: 64, rows: 32 }] },
  { label: 'Portrait', options: [{ cols: 16, rows: 32 }, { cols: 32, rows: 64 }] },
];

export const ALL_FORMATS: Format[] = FORMAT_GROUPS.flatMap(g => g.options);

const MIN_DIM = 8;

export function formatKey(f: Format): string {
  return `${f.cols}x${f.rows}`;
}

function clampViewport(v: Viewport, imgW: number, imgH: number, ratio: number): Viewport {
  // ratio = w / h
  let w = Math.min(v.w, imgW);
  let h = Math.min(v.h, imgH);
  // Snap to ratio (fit inside whichever dim is tighter)
  if (w / h > ratio) w = Math.round(h * ratio);
  else h = Math.round(w / ratio);
  w = Math.max(MIN_DIM, Math.min(imgW, Math.round(w)));
  h = Math.max(MIN_DIM, Math.min(imgH, Math.round(h)));
  // Re-snap once after clamping (in case min/max changed proportion)
  if (w / h > ratio) w = Math.max(MIN_DIM, Math.round(h * ratio));
  else h = Math.max(MIN_DIM, Math.round(w / ratio));
  const x = Math.round(Math.min(Math.max(0, v.x), imgW - w));
  const y = Math.round(Math.min(Math.max(0, v.y), imgH - h));
  return { x, y, w, h };
}

export function defaultViewport(imgW: number, imgH: number, format: Format): Viewport {
  const ratio = format.cols / format.rows;
  // Largest rectangle of `ratio` fully fitting inside the image (100%).
  let w = imgW;
  let h = w / ratio;
  if (h > imgH) { h = imgH; w = h * ratio; }
  return clampViewport(
    { x: (imgW - w) / 2, y: (imgH - h) / 2, w, h },
    imgW, imgH, ratio,
  );
}

interface Props {
  image: HTMLImageElement;
  format: Format;
  viewport: Viewport;
  onViewportChange: (v: Viewport) => void;
  /** When set, viewport w/h are forced to these source-pixel dimensions and
   * resize handles are hidden. Only pan remains active. Used for pixel-art
   * lock mode so each cell maps to one logical source pixel. */
  lockedSize?: { w: number; h: number } | null;
}

type DragMode =
  | { kind: 'pan'; sx: number; sy: number; ox: number; oy: number; ow: number; oh: number }
  | { kind: 'resize'; handle: Handle; sx: number; sy: number; v0: Viewport };

type Handle = 'n' | 's' | 'e' | 'w' | 'ne' | 'nw' | 'se' | 'sw';

function sameViewport(a: Viewport, b: Viewport): boolean {
  return a.x === b.x && a.y === b.y && a.w === b.w && a.h === b.h;
}

function sameViewportValues(a: Viewport, x: number, y: number, w: number, h: number): boolean {
  return a.x === x && a.y === y && a.w === w && a.h === h;
}

export default function ViewportPicker({ image, format, viewport, onViewportChange, lockedSize }: Props) {
  const wrapRef = useRef<HTMLDivElement>(null);
  const [scale, setScale] = useState(1);
  const [drag, setDrag] = useState<DragMode | null>(null);
  const ratio = format.cols / format.rows;
  const [draftViewport, setDraftViewport] = useState<Viewport>(() =>
    clampViewport(viewport, image.width, image.height, ratio),
  );
  const draftRef = useRef(draftViewport);
  const dragRef = useRef<DragMode | null>(null);
  const rafRef = useRef<number | null>(null);

  useEffect(() => {
    const el = wrapRef.current;
    if (!el) return;
    const compute = () => {
      const cw = el.clientWidth;
      const ch = Math.min(window.innerHeight * 0.55, 520);
      if (cw <= 0 || ch <= 0 || image.width <= 0 || image.height <= 0) return;
      const s = Math.min(cw / image.width, ch / image.height, 1);
      setScale(prev => Math.abs(prev - s) < 0.001 ? prev : s);
    };
    compute();
    const ro = new ResizeObserver(compute);
    ro.observe(el);
    return () => ro.disconnect();
  }, [image]);

  useEffect(() => {
    return () => {
      if (rafRef.current !== null) cancelAnimationFrame(rafRef.current);
    };
  }, []);

  const setDraftViewportFrame = useCallback((next: Viewport) => {
    draftRef.current = next;
    if (rafRef.current !== null) return;
    rafRef.current = requestAnimationFrame(() => {
      rafRef.current = null;
      setDraftViewport(draftRef.current);
    });
  }, []);

  const commitViewport = useCallback((next: Viewport) => {
    const clean = clampViewport(next, image.width, image.height, ratio);
    draftRef.current = clean;
    setDraftViewport(clean);
    if (!sameViewportValues(clean, viewport.x, viewport.y, viewport.w, viewport.h)) onViewportChange(clean);
  }, [image.width, image.height, ratio, viewport.x, viewport.y, viewport.w, viewport.h, onViewportChange]);

  // Re-snap only on external changes. During drag, keep the camera local so
  // the expensive mosaic pipeline does not run on every pointer frame.
  useEffect(() => {
    if (dragRef.current) return;
    const base = lockedSize
      ? { x: viewport.x, y: viewport.y, w: lockedSize.w, h: lockedSize.h }
      : { x: viewport.x, y: viewport.y, w: viewport.w, h: viewport.h };
    const c = clampViewport(base, image.width, image.height, ratio);
    // When locked, force exact lockedSize (overrides ratio snapping).
    if (lockedSize) {
      c.w = Math.min(image.width, lockedSize.w);
      c.h = Math.min(image.height, lockedSize.h);
      c.x = Math.min(Math.max(0, c.x), image.width - c.w);
      c.y = Math.min(Math.max(0, c.y), image.height - c.h);
    }
    if (!sameViewport(c, draftRef.current)) {
      draftRef.current = c;
      setDraftViewport(c);
    }
    if (!sameViewportValues(c, viewport.x, viewport.y, viewport.w, viewport.h)) {
      onViewportChange(c);
    }
  }, [ratio, image.width, image.height, viewport.x, viewport.y, viewport.w, viewport.h, lockedSize, onViewportChange]);

  const onPanDown = useCallback((e: React.PointerEvent) => {
    (e.target as HTMLElement).setPointerCapture(e.pointerId);
    const v = draftRef.current;
    const nextDrag: DragMode = { kind: 'pan', sx: e.clientX, sy: e.clientY, ox: v.x, oy: v.y, ow: v.w, oh: v.h };
    dragRef.current = nextDrag;
    setDrag(nextDrag);
  }, []);

  const onHandleDown = useCallback((handle: Handle) => (e: React.PointerEvent) => {
    e.stopPropagation();
    (e.target as HTMLElement).setPointerCapture(e.pointerId);
    const nextDrag: DragMode = { kind: 'resize', handle, sx: e.clientX, sy: e.clientY, v0: { ...draftRef.current } };
    dragRef.current = nextDrag;
    setDrag(nextDrag);
  }, []);

  const onPointerMove = useCallback((e: React.PointerEvent) => {
    if (!drag) return;
    const dx = (e.clientX - drag.sx) / scale;
    const dy = (e.clientY - drag.sy) / scale;

    if (drag.kind === 'pan') {
      let next: Viewport;
      if (lockedSize) {
        const w = Math.min(image.width, lockedSize.w);
        const h = Math.min(image.height, lockedSize.h);
        const x = Math.round(Math.min(Math.max(0, drag.ox + dx), image.width - w));
        const y = Math.round(Math.min(Math.max(0, drag.oy + dy), image.height - h));
        next = { x, y, w, h };
      } else {
        next = clampViewport(
          { x: drag.ox + dx, y: drag.oy + dy, w: drag.ow, h: drag.oh },
          image.width, image.height, ratio,
        );
      }
      if (!sameViewport(next, draftRef.current)) setDraftViewportFrame(next);
      return;
    }

    if (lockedSize) return; // no resize when locked


    // RESIZE — maintain ratio. Strategy: compute new w from horizontal handle
    // motion (or new h from vertical-only handles), then derive the other dim.
    const { handle, v0 } = drag;
    let x = v0.x, y = v0.y, w = v0.w, h = v0.h;

    const hasE = handle.includes('e');
    const hasW = handle.includes('w');
    const hasS = handle.includes('s');
    const hasN = handle.includes('n');

    if (hasE || hasW) {
      if (hasE) w = v0.w + dx;
      if (hasW) { w = v0.w - dx; x = v0.x + dx; }
      h = w / ratio;
      // Anchor vertically based on which corner
      if (hasN) y = v0.y + (v0.h - h);
      else if (!hasS) y = v0.y + (v0.h - h) / 2;
    } else if (hasS || hasN) {
      if (hasS) h = v0.h + dy;
      if (hasN) { h = v0.h - dy; y = v0.y + dy; }
      w = h * ratio;
      x = v0.x + (v0.w - w) / 2;
    }

    const next = clampViewport({ x, y, w, h }, image.width, image.height, ratio);
    if (!sameViewport(next, draftRef.current)) setDraftViewportFrame(next);
  }, [drag, scale, ratio, image.width, image.height, lockedSize, setDraftViewportFrame]);

  const onPointerUp = useCallback(() => {
    if (dragRef.current) commitViewport(draftRef.current);
    dragRef.current = null;
    setDrag(null);
  }, [commitViewport]);

  const dispW = image.width * scale;
  const dispH = image.height * scale;
  const rx = draftViewport.x * scale;
  const ry = draftViewport.y * scale;
  const rw = draftViewport.w * scale;
  const rh = draftViewport.h * scale;

  const handleStyle: React.CSSProperties = {
    position: 'absolute',
    width: 14, height: 14,
    background: 'hsl(var(--primary))',
    border: '2px solid hsl(var(--background))',
    borderRadius: 2,
    zIndex: 2,
  };

  const handles: { h: Handle; style: React.CSSProperties; cursor: string }[] = [
    { h: 'nw', style: { left: -7, top: -7 }, cursor: 'nwse-resize' },
    { h: 'ne', style: { right: -7, top: -7 }, cursor: 'nesw-resize' },
    { h: 'sw', style: { left: -7, bottom: -7 }, cursor: 'nesw-resize' },
    { h: 'se', style: { right: -7, bottom: -7 }, cursor: 'nwse-resize' },
    { h: 'n',  style: { left: '50%', top: -7, transform: 'translateX(-50%)' }, cursor: 'ns-resize' },
    { h: 's',  style: { left: '50%', bottom: -7, transform: 'translateX(-50%)' }, cursor: 'ns-resize' },
    { h: 'w',  style: { left: -7, top: '50%', transform: 'translateY(-50%)' }, cursor: 'ew-resize' },
    { h: 'e',  style: { right: -7, top: '50%', transform: 'translateY(-50%)' }, cursor: 'ew-resize' },
  ];

  return (
    <div className="space-y-2">
      <div ref={wrapRef} className="w-full">
        <div
          className="relative inline-block touch-none select-none rounded border border-border bg-card"
          style={{ width: dispW, height: dispH }}
          onPointerMove={onPointerMove}
          onPointerUp={onPointerUp}
          onPointerCancel={onPointerUp}
        >
          <img
            src={image.src}
            alt=""
            draggable={false}
            className="absolute inset-0 h-full w-full"
          />
          <div className="pointer-events-none absolute inset-x-0 top-0" style={{ height: ry, background: 'rgba(0,0,0,0.55)' }} />
          <div className="pointer-events-none absolute inset-x-0 bottom-0" style={{ top: ry + rh, background: 'rgba(0,0,0,0.55)' }} />
          <div className="pointer-events-none absolute left-0" style={{ top: ry, width: rx, height: rh, background: 'rgba(0,0,0,0.55)' }} />
          <div className="pointer-events-none absolute right-0" style={{ top: ry, left: rx + rw, height: rh, background: 'rgba(0,0,0,0.55)' }} />
          <div
            onPointerDown={onPanDown}
            className="absolute cursor-move"
            style={{
              left: rx, top: ry, width: rw, height: rh,
              outline: '2px solid hsl(var(--primary))',
              boxShadow: '0 0 0 1px rgba(255,255,255,0.6) inset',
              willChange: drag ? 'left, top, width, height' : 'auto',
            }}
          >
            {!lockedSize && handles.map(({ h, style, cursor }) => (
              <div
                key={h}
                onPointerDown={onHandleDown(h)}
                style={{ ...handleStyle, ...style, cursor }}
              />
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
