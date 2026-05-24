import { useEffect, useState } from 'react';
import { ArrowRight } from 'lucide-react';

const STORAGE_KEY = 'pixel-grid-onboarded-v1';

export function hasSeenOnboarding(): boolean {
  try {
    return localStorage.getItem(STORAGE_KEY) === '1';
  } catch {
    return false;
  }
}

export function markOnboardingSeen() {
  try {
    localStorage.setItem(STORAGE_KEY, '1');
  } catch {
    /* ignore */
  }
}

export function resetOnboarding() {
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch {
    /* ignore */
  }
}

interface Props {
  open: boolean;
  onDismiss: () => void;
}

/** Minimal first-visit welcome screen. Non-blocking after dismiss. */
export default function WelcomeOverlay({ open, onDismiss }: Props) {
  const [mounted, setMounted] = useState(open);
  const [leaving, setLeaving] = useState(false);

  useEffect(() => {
    if (open) {
      setMounted(true);
      setLeaving(false);
    }
  }, [open]);

  if (!mounted) return null;

  const handleDismiss = () => {
    setLeaving(true);
    setTimeout(() => {
      setMounted(false);
      onDismiss();
    }, 280);
  };

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="welcome-title"
      className={`fixed inset-0 z-[100] flex items-center justify-center bg-background/95 backdrop-blur-md transition-opacity duration-300 ${
        leaving ? 'opacity-0' : 'opacity-100'
      }`}
    >
      <div
        className={`relative mx-4 w-full max-w-xl rounded-2xl border border-border bg-card p-8 sm:p-12 shadow-2xl transition-all duration-300 ${
          leaving ? 'translate-y-2 opacity-0' : 'translate-y-0 opacity-100'
        }`}
      >
        {/* Visual: animated wood mosaic */}
        <div className="mb-8 flex justify-center">
          <MosaicMark />
        </div>

        <div className="text-center space-y-3">
          <div className="font-mono text-[10px] uppercase tracking-[0.25em] text-muted-foreground">
            Pixel Grid · Bois
          </div>
          <h1
            id="welcome-title"
            className="font-display text-2xl sm:text-3xl font-bold leading-tight text-foreground"
          >
            Transformez vos images en mosaïques bois.
          </h1>
          <p className="font-mono text-xs sm:text-sm text-muted-foreground max-w-sm mx-auto leading-relaxed">
            Importez une photo, choisissez un format, laissez le générateur composer votre tableau.
          </p>
        </div>

        <div className="mt-8 flex justify-center">
          <button
            onClick={handleDismiss}
            className="group inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 font-mono text-sm font-bold uppercase tracking-wider text-primary-foreground transition-all hover:opacity-90 hover:gap-3"
          >
            Commencer
            <ArrowRight size={16} className="transition-transform group-hover:translate-x-0.5" />
          </button>
        </div>
      </div>
    </div>
  );
}

/** Animated 6×4 wood-palette grid as a brand mark. */
function MosaicMark() {
  // Curated wood tones (sand → walnut → ebony) to evoke the product.
  const palette = [
    '#d9b88a', '#c89b6a', '#a87447', '#6f4a2a',
    '#e7cfa3', '#b78757', '#8d5a32', '#4a2f1a',
    '#cfa97a', '#9d6f44', '#75451e', '#3a230f',
  ];
  return (
    <div
      className="grid gap-1 rounded-md p-1.5 bg-muted/40 border border-border"
      style={{ gridTemplateColumns: 'repeat(6, 1fr)' }}
      aria-hidden
    >
      {Array.from({ length: 24 }).map((_, i) => {
        const c = palette[(i * 7) % palette.length];
        return (
          <span
            key={i}
            className="block h-5 w-5 sm:h-6 sm:w-6 rounded-[3px] animate-in fade-in zoom-in-50"
            style={{
              backgroundColor: c,
              animationDelay: `${i * 35}ms`,
              animationDuration: '500ms',
              animationFillMode: 'backwards',
            }}
          />
        );
      })}
    </div>
  );
}
