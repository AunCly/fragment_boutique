import { useRef, useState } from 'react';
import { Upload, RefreshCw } from 'lucide-react';

interface Props {
  onImageLoaded: (img: HTMLImageElement, imageData: ImageData) => void;
  /** Currently loaded image preview (data URL or object URL). */
  currentImageUrl?: string | null;
}

const MAX_DIMENSION = 2048;
const ACCEPTED = ['image/png', 'image/jpeg', 'image/bmp', 'image/webp'];

export default function ImageImporter({ onImageLoaded, currentImageUrl }: Props) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadImage = (file: File) => {
    if (file && !file.type.startsWith('image/')) {
      setError("Format non pris en charge. Formats acceptés : PNG, JPG, BMP, WEBP.");
      return;
    }
    setError(null);
    setLoading(true);
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      let { width, height } = img;
      if (width > MAX_DIMENSION || height > MAX_DIMENSION) {
        const scale = MAX_DIMENSION / Math.max(width, height);
        width = Math.round(width * scale);
        height = Math.round(height * scale);
      }
      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      const ctx = canvas.getContext('2d')!;
      ctx.drawImage(img, 0, 0, width, height);
      const data = ctx.getImageData(0, 0, width, height);

      const finalImg = new Image();
      finalImg.onload = () => {
        onImageLoaded(finalImg, data);
        setLoading(false);
        URL.revokeObjectURL(url);
      };
      finalImg.src = canvas.toDataURL('image/png');
    };
    img.onerror = () => {
      setLoading(false);
      setError("Impossible de lire ce fichier. Formats acceptés : PNG, JPG, BMP, WEBP.");
    };
    img.src = url;
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file) loadImage(file);
  };

  // ─── With image: persistent preview + replace action ───
  if (currentImageUrl) {
    return (
      <div className="space-y-3">
        <div className="flex justify-center">
        <div
          onClick={() => inputRef.current?.click()}
          onDrop={handleDrop}
          onDragOver={(e) => e.preventDefault()}
          className="group relative inline-block max-w-full overflow-hidden rounded-2xl border border-border bg-card cursor-pointer soft-shadow"
        >
          <img
            src={currentImageUrl}
            alt="Image importée"
            className="block max-h-[60vh] max-w-full h-auto w-auto"
          />
          <div className="absolute inset-0 flex items-end justify-end p-3 opacity-0 transition-opacity group-hover:opacity-100">
            <span className="inline-flex items-center gap-1.5 rounded-full bg-foreground/85 px-4 py-2 font-sans-soft text-xs text-background backdrop-blur">
              <RefreshCw size={12} /> Remplacer l'image
            </span>
          </div>
        </div>
        </div>
        {error && (
          <div className="rounded-lg border border-destructive/40 bg-destructive/5 px-4 py-3 font-sans-soft text-sm text-destructive">
            {error}
          </div>
        )}
        <input
          ref={inputRef}
          type="file"
          accept="image/*"
          className="hidden"
          onChange={(e) => {
            const file = e.target.files?.[0];
            if (file) loadImage(file);
            e.target.value = '';
          }}
        />
      </div>
    );
  }

  // ─── Empty state ───
  return (
    <div className="space-y-3">
      <div
        onDrop={handleDrop}
        onDragOver={(e) => e.preventDefault()}
        onClick={() => inputRef.current?.click()}
        className={`flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-border bg-secondary p-10 sm:p-14 cursor-pointer transition-colors hover:border-primary hover:bg-muted ${loading ? 'opacity-50 pointer-events-none' : ''}`}
      >
        <Upload size={28} className="text-muted-foreground" />
        {loading ? (
          <p className="font-sans-soft text-sm text-muted-foreground">Traitement en cours…</p>
        ) : (
          <p className="font-sans-soft text-sm text-muted-foreground text-center">
            Glissez une image ou <span className="text-primary underline">parcourir</span>
          </p>
        )}
      </div>
      {error && (
        <div className="rounded-lg border border-destructive/40 bg-destructive/5 px-4 py-3 font-sans-soft text-sm text-destructive">
          {error}
        </div>
      )}
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={(e) => {
          const file = e.target.files?.[0];
          if (file) loadImage(file);
          e.target.value = '';
        }}
      />
    </div>
  );
}
