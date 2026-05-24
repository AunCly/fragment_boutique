interface Props {
  textures: { url: string | null; hex: string }[];
}

/** 2×2 montage of texture swatches for a wood collection card. */
export default function CollectionPreview({ textures }: Props) {
  const cells = [0, 1, 2, 3].map(i => textures[i % Math.max(1, textures.length)] ?? { url: null, hex: '#b08a5e' });
  return (
    <div className="grid h-full w-full grid-cols-2 grid-rows-2 gap-0.5 bg-border/40">
      {cells.map((c, i) => (
        <div
          key={i}
          className="overflow-hidden"
          style={{
            backgroundColor: c.hex,
            backgroundImage: c.url ? `url(${c.url})` : undefined,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
          }}
        />
      ))}
    </div>
  );
}
