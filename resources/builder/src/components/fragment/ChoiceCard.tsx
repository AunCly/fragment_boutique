import { ReactNode } from 'react';

interface Props {
  title: string;
  description?: string;
  active?: boolean;
  onSelect: () => void;
  /** Visual preview slot — texture montage, format icon, etc. */
  preview?: ReactNode;
  children?: ReactNode;
}

export default function ChoiceCard({ title, description, active, onSelect, preview, children }: Props) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className={`group relative flex w-full flex-col overflow-hidden rounded-2xl border bg-card text-left transition-all duration-500 ease-out
        ${active
          ? 'border-accent ring-2 ring-accent/30 soft-shadow-lg -translate-y-0.5'
          : 'border-border hover:border-accent/60 soft-shadow hover:soft-shadow-lg hover:-translate-y-0.5'}`}
    >
      {preview && (
        <div className="aspect-[16/9] w-full overflow-hidden bg-muted">
          {preview}
        </div>
      )}
      <div className="p-5 sm:p-6">
        <h3 className="font-serif text-xl sm:text-2xl text-foreground leading-tight">{title}</h3>
        {description && (
          <p className="mt-2 font-sans-soft text-sm leading-relaxed text-muted-foreground">{description}</p>
        )}
        {children}
      </div>
      {active && (
        <span className="absolute right-4 top-4 inline-flex h-7 w-7 items-center justify-center rounded-full bg-accent text-accent-foreground text-xs">✓</span>
      )}
    </button>
  );
}
