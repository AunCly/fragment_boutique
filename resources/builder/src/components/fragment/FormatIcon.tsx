interface Props {
  ratio: 'square' | 'landscape' | 'portrait';
}

export default function FormatIcon({ ratio }: Props) {
  const dims = ratio === 'square' ? { w: 80, h: 80 } : ratio === 'landscape' ? { w: 110, h: 60 } : { w: 60, h: 110 };
  return (
    <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-muted to-secondary">
      <div
        className="rounded-md border-2 border-accent/70 bg-card/80 soft-shadow"
        style={{ width: dims.w, height: dims.h }}
      />
    </div>
  );
}
