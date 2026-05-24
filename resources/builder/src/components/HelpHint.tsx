import { HelpCircle } from 'lucide-react';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

interface Props {
  title?: string;
  children: React.ReactNode;
  ariaLabel?: string;
}

/**
 * Tiny inline (?) icon that opens a small popover with a contextual hint.
 * Non-blocking, never interrupts the workflow.
 */
export default function HelpHint({ title, children, ariaLabel = 'Aide' }: Props) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          aria-label={ariaLabel}
          onClick={(e) => e.stopPropagation()}
          className="inline-flex h-5 w-5 items-center justify-center rounded-full text-muted-foreground/70 hover:text-foreground hover:bg-accent/60 transition-colors"
        >
          <HelpCircle size={13} strokeWidth={2} />
        </button>
      </PopoverTrigger>
      <PopoverContent
        side="top"
        align="start"
        className="w-64 p-3 text-xs"
        onClick={(e) => e.stopPropagation()}
      >
        {title && (
          <div className="font-display text-[11px] font-bold uppercase tracking-widest text-foreground mb-1">
            {title}
          </div>
        )}
        <div className="font-mono text-[11px] leading-snug text-muted-foreground space-y-1.5">
          {children}
        </div>
      </PopoverContent>
    </Popover>
  );
}
