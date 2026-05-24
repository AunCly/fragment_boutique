import { useEffect, useRef, useState, ReactNode } from 'react';
import { ChevronUp, ChevronDown } from 'lucide-react';

interface Props {
  /** Hidden until first ready. */
  visible: boolean;
  /** A small swatch / mini-thumb shown in the collapsed handle. */
  thumb?: ReactNode;
  /** Short label shown next to the thumb (e.g. "32 × 32 · 5 essences"). */
  label?: string;
  /** Full preview content (rendered when expanded). */
  children: ReactNode;
}

/**
 * Floating, draggable Fragment preview.
 *
 * Collapsed: bottom handle bar with mini-thumb + chevron.
 * Expanded: large sheet that overlays the bottom of the viewport.
 * Toggling by tapping the handle or vertical swipe.
 */
export default function FloatingPreview({ visible, thumb, label, children }: Props) {
  const [open, setOpen] = useState(false);
  const dragRef = useRef<{ startY: number; openAtStart: boolean } | null>(null);

  // Close with Escape when open
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open]);

  if (!visible) return null;

  const onPointerDown = (e: React.PointerEvent) => {
    dragRef.current = { startY: e.clientY, openAtStart: open };
    (e.target as HTMLElement).setPointerCapture(e.pointerId);
  };
  const onPointerMove = (_e: React.PointerEvent) => {
    // no-op — we decide on pointerup based on total dy
  };
  const onPointerUp = (e: React.PointerEvent) => {
    const start = dragRef.current; dragRef.current = null;
    if (!start) return;
    const dy = e.clientY - start.startY;
    if (Math.abs(dy) < 6) {
      // tap
      setOpen(v => !v);
      return;
    }
    if (dy < -20) setOpen(true);
    else if (dy > 20) setOpen(false);
  };

  return (
    <>
      {/* Sheet — non-blocking, site stays navigable when expanded */}
      <div
        className={`fixed inset-x-0 bottom-0 z-50 mx-auto w-full max-w-3xl rounded-t-3xl border border-border/80 bg-card soft-shadow-lg transition-transform duration-500 ease-[cubic-bezier(.22,.61,.36,1)]`}
        style={{
          transform: open ? 'translateY(0)' : 'translateY(calc(100% - 76px))',
        }}
      >
        {/* Drag handle */}
        <div
          onPointerDown={onPointerDown}
          onPointerMove={onPointerMove}
          onPointerUp={onPointerUp}
          className="flex h-[76px] cursor-pointer select-none items-center gap-3 px-5 touch-none"
        >
          <span className="mx-auto absolute left-1/2 top-2 h-1 w-10 -translate-x-1/2 rounded-full bg-muted-foreground/30" />
          {thumb && (
            <span className="block h-11 w-11 shrink-0 overflow-hidden rounded-lg border border-border bg-muted">
              {thumb}
            </span>
          )}
          <div className="flex-1 min-w-0">
            <div className="font-serif text-base text-foreground leading-tight">Votre Fragment</div>
            {label && (
              <div className="font-sans-soft text-[11px] text-muted-foreground truncate">{label}</div>
            )}
          </div>
          <span className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-secondary text-foreground">
            {open ? <ChevronDown size={18} /> : <ChevronUp size={18} />}
          </span>
        </div>

        {/* Body */}
        <div
          className="overflow-y-auto px-5 pb-8"
          style={{ maxHeight: 'min(75vh, 720px)' }}
        >
          {children}
        </div>
      </div>
    </>
  );
}
