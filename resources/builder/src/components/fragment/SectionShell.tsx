import { forwardRef, ReactNode } from 'react';

interface Props {
  title: string;
  subtitle?: string;
  children: ReactNode;
  /** Smaller minHeight when no big interactive content. */
  compact?: boolean;
  /** Render a thin separator at the top to clarify the block. */
  divider?: boolean;
}

const SectionShell = forwardRef<HTMLElement, Props>(function SectionShell(
  { title, subtitle, children, compact = false, divider = false }, ref,
) {
  return (
    <section
      ref={ref}
      className={`relative w-full px-5 sm:px-8 ${compact ? 'py-10 sm:py-14' : 'py-14 sm:py-20'} fade-in-up`}
    >
      <div className="mx-auto max-w-3xl lg:mx-0 lg:max-w-none">
        {divider && (
          <div className="mb-10 sm:mb-14 h-px w-16 bg-border" aria-hidden />
        )}
        <h2 className="font-serif text-3xl sm:text-4xl leading-[1.05] tracking-tight text-foreground">
          {title}
        </h2>
        {subtitle && (
          <p className="mt-3 font-sans-soft text-base leading-relaxed text-muted-foreground max-w-xl">
            {subtitle}
          </p>
        )}
        <div className="mt-8">{children}</div>
      </div>
    </section>
  );
});

export default SectionShell;
