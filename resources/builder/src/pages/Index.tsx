import { useState, useRef, useMemo, useEffect } from 'react';
import { DEFAULT_PALETTE, PaletteColor, analyzeGridColors, ColorAnalysis } from '@/lib/pixelArt';
import { runPipeline } from '@/lib/pipeline';
import { LibraryEntry, loadLibrary } from '@/lib/colorLibrary';
import { getSymbolForHex, assignSymbolsToPalette } from '@/lib/symbolMapping';
import { suggestPalette, getLibrarySource, SuggestionMode, DEFAULT_FILTER_TAGS } from '@/lib/paletteSuggestion';
import { loadSession, saveSession, imageToDataUrl, dataUrlToImage } from '@/lib/sessionPersistence';
import { detectPixelArt, PixelArtDetection } from '@/lib/pixelArtDetect';
import ImageImporter from '@/components/ImageImporter';
import ViewportPicker, { defaultViewport, Viewport, Format } from '@/components/ViewportPicker';
import PaletteEditor from '@/components/PaletteEditor';
import PixelGrid from '@/components/PixelGrid';
import { ChevronDown, ChevronUp, RotateCcw, Palette } from 'lucide-react';
import SectionShell from '@/components/fragment/SectionShell';
import ChoiceCard from '@/components/fragment/ChoiceCard';
import FloatingPreview from '@/components/fragment/FloatingPreview';
import CollectionPreview from '@/components/fragment/CollectionPreview';
import RetouchLibrary from '@/components/fragment/RetouchLibrary';
import DetailPreview from '@/components/fragment/DetailPreview';
import LibraryBrowserModal from '@/components/fragment/LibraryBrowserModal';

// ─── Format presets — loaded from DB via window injection, hardcoded fallback ───
type Shape = 'square' | 'landscape' | 'portrait';

interface FormatOption {
  key: string;
  shape: Shape;
  label: string;   // dimension label e.g. "16 × 16 cm"
  format: Format;  // cols = width cm, rows = height cm (tableSizeCm uses these directly)
}

const FALLBACK_FORMAT_OPTIONS: FormatOption[] = [
  { key: 'square-s',    shape: 'square',    label: '16 × 16 cm', format: { cols: 16, rows: 16 } },
  { key: 'square-l',    shape: 'square',    label: '32 × 32 cm', format: { cols: 32, rows: 32 } },
  { key: 'landscape-s', shape: 'landscape', label: '32 × 16 cm', format: { cols: 32, rows: 16 } },
  { key: 'landscape-l', shape: 'landscape', label: '64 × 32 cm', format: { cols: 64, rows: 32 } },
  { key: 'portrait-s',  shape: 'portrait',  label: '16 × 32 cm', format: { cols: 16, rows: 32 } },
  { key: 'portrait-l',  shape: 'portrait',  label: '32 × 64 cm', format: { cols: 32, rows: 64 } },
];

const FORMAT_OPTIONS: FormatOption[] = (window as any).FRAGMENT_FORMATS ?? FALLBACK_FORMAT_OPTIONS;

const SHAPE_LABEL: Record<Shape, string> = { square: 'Carré', landscape: 'Paysage', portrait: 'Portrait' };
const DEFAULT_FORMAT: Format = FORMAT_OPTIONS[1].format; // 32×32

// Detail level → physical pixel size (mm). Lower mm = higher resolution.
type Detail = 'detailed' | 'balanced';
const DETAIL_MM: Record<Detail, number> = { detailed: 5, balanced: 10 };
const PRICE_PER_CM2 = 0.8; // €/cm² — placeholder

function shapeFromFormat(f: Format): Shape {
  if (f.cols === f.rows) return 'square';
  return f.cols > f.rows ? 'landscape' : 'portrait';
}
function detailFromMm(mm: number): Detail {
  return mm <= 5 ? 'detailed' : 'balanced';
}
function formatKey(f: Format): string | null {
  return FORMAT_OPTIONS.find(o => o.format.cols === f.cols && o.format.rows === f.rows)?.key ?? null;
}

export default function Index() {
  const initial = useMemo(() => loadSession() ?? {}, []);

  // ─── Core state (unchanged logic) ─────────────────────────────
  const [palette, setPalette] = useState<PaletteColor[]>(initial.palette ?? DEFAULT_PALETTE);
  const [sourceImageData, setSourceImageData] = useState<ImageData | null>(null);
  const [imgSize, setImgSize] = useState<{ w: number; h: number }>(initial.imgSize ?? { w: 0, h: 0 });
  const [format, setFormat] = useState<Format>(initial.format ?? DEFAULT_FORMAT);
  const [viewport, setViewport] = useState<Viewport>(initial.viewport ?? { x: 0, y: 0, w: 0, h: 0 });
  const [pixelSizeMm, setPixelSizeMm] = useState<number>(initial.pixelSizeMm ?? 10);
  const [manualEditsObj, setManualEditsObj] = useState<Record<string, string>>(initial.manualEdits ?? {});
  const manualEdits = useMemo(() => new Map(Object.entries(manualEditsObj)), [manualEditsObj]);

  const [editMode, setEditMode] = useState(false);
  const [selectedEditColor, setSelectedEditColor] = useState<string | null>(null);

  const [originalImg, setOriginalImg] = useState<HTMLImageElement | null>(null);
  const [pixelArtInfo, setPixelArtInfo] = useState<PixelArtDetection | null>(null);
  const [forcePhoto, setForcePhoto] = useState(false);
  const pixelSizeCm = pixelSizeMm / 10;

  const [suggestionMode, setSuggestionMode] = useState<SuggestionMode>(initial.suggestionMode ?? 'filtered');
  const [suggestionTick, setSuggestionTick] = useState(0);
  const [libraryTick, setLibraryTick] = useState(0);
  const skipNextSuggest = useRef<boolean>(!!initial.palette && !!initial.imageDataUrl);

  const [refineOpen, setRefineOpen] = useState(false);
  const [libraryOpen, setLibraryOpen] = useState(false);
  const [importError, setImportError] = useState<string | null>(null);
  const [imageDataUrl, setImageDataUrl] = useState<string | null>(initial.imageDataUrl ?? null);
  const [pastHero, setPastHero] = useState(false);

  const canvasRef = useRef<HTMLCanvasElement>(null);

  // ─── Section refs for guided scroll ───────────────────────────
  const refImport = useRef<HTMLElement>(null);
  const refFormat = useRef<HTMLElement>(null);
  const refDetail = useRef<HTMLElement>(null);
  const refCollection = useRef<HTMLElement>(null);
  const refRefine = useRef<HTMLElement>(null);
  const refValidation = useRef<HTMLElement>(null);

  const scrollTo = (ref: React.RefObject<HTMLElement | null>) => {
    setTimeout(() => {
      const el = ref.current;
      if (!el) return;
      const startY = window.scrollY;
      const targetY = el.getBoundingClientRect().top + startY - 8;
      const dist = targetY - startY;
      if (Math.abs(dist) < 4) return;
      const duration = Math.min(1400, 700 + Math.abs(dist) * 0.25);
      const t0 = performance.now();
      const ease = (t: number) => 1 - Math.pow(1 - t, 4); // easeOutQuart — slows at end
      const step = (now: number) => {
        const t = Math.min(1, (now - t0) / duration);
        window.scrollTo(0, startY + dist * ease(t));
        if (t < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    }, 240);
  };

  // ─── Restore image (async) ────────────────────────────────────
  useEffect(() => {
    if (!initial.imageDataUrl) return;
    let cancelled = false;
    dataUrlToImage(initial.imageDataUrl).then(res => {
      if (cancelled || !res) return;
      setOriginalImg(res.img);
      setSourceImageData(res.data);
      setImgSize({ w: res.img.width, h: res.img.height });
      setPixelArtInfo(detectPixelArt(res.data, res.img.width, res.img.height));
      setForcePhoto(false);
      const persisted = initial.viewport as Partial<Viewport> | undefined;
      const w = Number(persisted?.w) || 0;
      const h = Number(persisted?.h) || 0;
      if (!persisted || w <= 0 || h <= 0) {
        setViewport(defaultViewport(res.img.width, res.img.height, initial.format ?? DEFAULT_FORMAT));
      } else {
        setViewport({ x: Number(persisted.x) || 0, y: Number(persisted.y) || 0, w, h });
      }
    });
    return () => { cancelled = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Show side preview only once user scrolls past the hero into the import section.
  useEffect(() => {
    const el = refImport.current;
    if (!el) return;
    const onScroll = () => {
      const top = el.getBoundingClientRect().top;
      setPastHero(top < window.innerHeight * 0.6);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, [originalImg]);


  // ─── Derived: grid resolution & pixel-art lock ────────────────
  const tableSizeCm = useMemo(() => ({ w: format.cols, h: format.rows }), [format]);
  const gridSize = useMemo(() => ({
    cols: Math.max(1, Math.round((format.cols * 10) / pixelSizeMm)),
    rows: Math.max(1, Math.round((format.rows * 10) / pixelSizeMm)),
  }), [format, pixelSizeMm]);

  const pixelArtLockActive = useMemo(() => {
    if (forcePhoto || !pixelArtInfo?.isPixelArt) return false;
    return gridSize.cols <= pixelArtInfo.logicalWidth && gridSize.rows <= pixelArtInfo.logicalHeight;
  }, [forcePhoto, pixelArtInfo, gridSize.cols, gridSize.rows]);

  const lockedSize = useMemo(() => {
    if (!pixelArtLockActive || !pixelArtInfo) return null;
    return { w: gridSize.cols * pixelArtInfo.upscaleFactor, h: gridSize.rows * pixelArtInfo.upscaleFactor };
  }, [pixelArtLockActive, pixelArtInfo, gridSize.cols, gridSize.rows]);

  // ─── Viewport extraction ──────────────────────────────────────
  const viewportImageData = useMemo<ImageData | null>(() => {
    if (!sourceImageData) return null;
    const srcW = sourceImageData.width, srcH = sourceImageData.height;
    if (!srcW || !srcH) return null;
    const vwRaw = Number(viewport?.w) || 0;
    const vhRaw = Number(viewport?.h) || 0;
    if (vwRaw <= 0 || vhRaw <= 0) return null;
    const C = gridSize.cols | 0, R = gridSize.rows | 0;
    if (C <= 0 || R <= 0) return null;
    const vw = Math.max(1, Math.min(vwRaw, srcW));
    const vh = Math.max(1, Math.min(vhRaw, srcH));
    const ox = Math.min(Math.max(0, Number(viewport?.x) || 0), srcW - vw);
    const oy = Math.min(Math.max(0, Number(viewport?.y) || 0), srcH - vh);
    const out = new Uint8ClampedArray(C * R * 4);
    const src = sourceImageData.data;
    if (pixelArtLockActive && pixelArtInfo && vw === C * pixelArtInfo.upscaleFactor && vh === R * pixelArtInfo.upscaleFactor) {
      const K = pixelArtInfo.upscaleFactor;
      const half = K >> 1;
      for (let gy = 0; gy < R; gy++) {
        const sy = Math.min(srcH - 1, oy + gy * K + half);
        for (let gx = 0; gx < C; gx++) {
          const sx = Math.min(srcW - 1, ox + gx * K + half);
          const i = (sy * srcW + sx) * 4;
          const di = (gy * C + gx) * 4;
          out[di] = src[i]; out[di + 1] = src[i + 1]; out[di + 2] = src[i + 2]; out[di + 3] = src[i + 3];
        }
      }
      return new ImageData(out, C, R);
    }
    for (let gy = 0; gy < R; gy++) {
      const y0 = oy + (gy * vh) / R;
      const y1 = oy + ((gy + 1) * vh) / R;
      const yStart = Math.floor(y0);
      const yEnd = Math.max(yStart + 1, Math.ceil(y1));
      for (let gx = 0; gx < C; gx++) {
        const x0 = ox + (gx * vw) / C;
        const x1 = ox + ((gx + 1) * vw) / C;
        const xStart = Math.floor(x0);
        const xEnd = Math.max(xStart + 1, Math.ceil(x1));
        let r = 0, g = 0, b = 0, a = 0, n = 0;
        for (let y = yStart; y < yEnd && y < srcH; y++) {
          for (let x = xStart; x < xEnd && x < srcW; x++) {
            const i = (y * srcW + x) * 4;
            r += src[i]; g += src[i + 1]; b += src[i + 2]; a += src[i + 3];
            n++;
          }
        }
        const di = (gy * C + gx) * 4;
        out[di] = r / n; out[di + 1] = g / n; out[di + 2] = b / n; out[di + 3] = a / n;
      }
    }
    return new ImageData(out, C, R);
  }, [sourceImageData, viewport.x, viewport.y, viewport.w, viewport.h, gridSize.cols, gridSize.rows, pixelArtLockActive, pixelArtInfo]);

  const mappedGrid = useMemo(() => {
    if (!viewportImageData) return null;
    return runPipeline({
      imageData: viewportImageData,
      imageWidth: gridSize.cols,
      imageHeight: gridSize.rows,
      gridCols: gridSize.cols,
      gridRows: gridSize.rows,
      palette,
      manualEdits,
    });
  }, [viewportImageData, gridSize.cols, gridSize.rows, palette, manualEdits]);

  const gridColors = mappedGrid?.rawColors ?? [];

  const sourceAnalysis = useMemo<ColorAnalysis | null>(() => {
    if (!viewportImageData) return null;
    const w = viewportImageData.width, h = viewportImageData.height;
    const grid: [number, number, number][][] = [];
    for (let y = 0; y < h; y++) {
      const row: [number, number, number][] = [];
      for (let x = 0; x < w; x++) {
        const i = (y * w + x) * 4;
        row.push([viewportImageData.data[i], viewportImageData.data[i + 1], viewportImageData.data[i + 2]]);
      }
      grid.push(row);
    }
    return analyzeGridColors(grid);
  }, [viewportImageData]);

  useEffect(() => {
    if (!sourceAnalysis || suggestionMode === 'custom') return;
    if (skipNextSuggest.current) { skipNextSuggest.current = false; return; }
    const source = getLibrarySource(suggestionMode);
    const next = suggestPalette(sourceAnalysis, source);
    if (next.length === 0) return;
    setPalette(next);
    setManualEditsObj({});
    if (selectedEditColor && !next.some(c => c.hex.toLowerCase() === selectedEditColor.toLowerCase())) {
      setSelectedEditColor(next[0].hex);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sourceAnalysis, suggestionMode, suggestionTick, libraryTick]);

  const handleImageLoaded = (img: HTMLImageElement, data: ImageData) => {
    setImportError(null);
    setOriginalImg(img);
    setSourceImageData(data);
    setImgSize({ w: img.width, h: img.height });
    setManualEditsObj({});
    const detection = detectPixelArt(data, img.width, img.height);
    setPixelArtInfo(detection);
    setForcePhoto(false);

    // Auto-select a coherent format based on the image's aspect ratio.
    // Always pick the smallest size of that shape + detailed pixel (5 mm)
    // so the first render looks crisp out of the box. User can change after.
    const ratio = img.width / img.height;
    const autoShape: Shape = ratio > 1.15 ? 'landscape' : ratio < 0.87 ? 'portrait' : 'square';
    const autoOpt = FORMAT_OPTIONS.find(o => o.shape === autoShape) ?? FORMAT_OPTIONS[0];
    setFormat(autoOpt.format);
    setPixelSizeMm(DETAIL_MM.detailed);
    setViewport(defaultViewport(img.width, img.height, autoOpt.format));

    const url = imageToDataUrl(img);
    if (url) {
      setImageDataUrl(url);
      saveSession({ imageDataUrl: url, imgSize: { w: img.width, h: img.height } });
    }
  };

  const handleFormatSelect = (opt: FormatOption) => {
    setManualEditsObj({});
    setFormat(opt.format);
    if (originalImg) setViewport(defaultViewport(originalImg.width, originalImg.height, opt.format));
  };

  const handleDetailSelect = (d: Detail) => {
    setPixelSizeMm(DETAIL_MM[d]);
  };

  const handleCollectionSelect = (mode: 'filtered' | 'all') => {
    setSuggestionMode(mode);
    setSuggestionTick(t => t + 1);
  };

  const handlePaletteChange = (newPalette: PaletteColor[]) => {
    const validHexes = new Set(newPalette.map(c => c.hex.toLowerCase()));
    setManualEditsObj(prev => {
      const next: Record<string, string> = {};
      for (const [k, v] of Object.entries(prev)) if (validHexes.has(v.toLowerCase())) next[k] = v;
      return next;
    });
    if (selectedEditColor && !validHexes.has(selectedEditColor.toLowerCase())) {
      setSelectedEditColor(newPalette[0]?.hex ?? null);
    }
    setPalette(newPalette);
  };

  useEffect(() => {
    saveSession({
      palette, imgSize, format, viewport, pixelSizeMm, suggestionMode,
      manualEdits: manualEditsObj,
    });
  }, [palette, imgSize, format, viewport, pixelSizeMm, suggestionMode, manualEditsObj]);

  const handleManualPaletteChange = (newPalette: PaletteColor[]) => {
    if (suggestionMode !== 'custom') setSuggestionMode('custom');
    handlePaletteChange(newPalette);
  };

  const handleEditPixel = (row: number, col: number) => {
    if (!editMode || !selectedEditColor) return;
    const key = `${row},${col}`;
    setManualEditsObj(prev => {
      if (prev[key]?.toLowerCase() === selectedEditColor.toLowerCase()) return prev;
      return { ...prev, [key]: selectedEditColor };
    });
  };

  const handleResetEdits = () => setManualEditsObj({});

  const handleAddToCart = () => {
    const shapeLabel = currentShape === 'square' ? 'Carré' : currentShape === 'landscape' ? 'Paysage' : 'Portrait';
    const detailLabel = currentDetail === 'detailed' ? 'Détaillé' : 'Équilibré';
    const title = `Fragment · ${shapeLabel} ${tableSizeCm.w}×${tableSizeCm.h} cm · ${detailLabel} · ${palette.length} essences`;
    window.dispatchEvent(new CustomEvent('fragment:add-to-cart', {
      detail: { title, price: priceEstimate, currency: 'EUR' },
    }));
  };

  const hasManualEdits = Object.keys(manualEditsObj).length > 0;

  // ─── Collection preview textures (random samples from library) ────────
  const collectionPreviews = useMemo(() => {
    const lib = loadLibrary();
    const filtered = lib.filter(e => DEFAULT_FILTER_TAGS.every(t => e.tags.includes(t)));
    const shuffle = <T,>(arr: T[]): T[] => {
      const a = [...arr];
      for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
      }
      return a;
    };
    const pick = (entries: typeof lib) =>
      shuffle(entries).slice(0, 4).map(e => ({ url: e.textureUrl ?? null, hex: e.hex }));
    return {
      filtered: pick(filtered.length ? filtered : lib),
      all: pick(lib),
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [libraryTick, suggestionTick]);

  // ─── Derived UI labels ────────────────────────────────────────
  const currentShape = shapeFromFormat(format);
  const currentDetail = detailFromMm(pixelSizeMm);
  const areaCm2 = tableSizeCm.w * tableSizeCm.h;
  const priceEstimate = Math.round(areaCm2 * PRICE_PER_CM2);
  const previewLabel = originalImg
    ? `${tableSizeCm.w}×${tableSizeCm.h} cm · ${palette.length} essences`
    : 'Importez une image pour commencer';
  const previewThumb = palette[0]?.textureUrl
    ? <img src={palette[0].textureUrl} alt="" className="h-full w-full object-cover" />
    : <span className="block h-full w-full" style={{ backgroundColor: palette[0]?.hex ?? '#b08a5e' }} />;

  return (
    <div className="fragment-theme min-h-screen bg-background text-foreground">
      {/* ─── Workflow wrapper ── on lg, content sits in the left 50% (centered at 40vw) ─── */}
      <div className="lg:w-1/2 lg:pl-[5vw] lg:pr-[5vw]">


      {/* ─── IMPORT ─── */}
      <SectionShell ref={refImport} title="Choisissez une image" subtitle="Importez une photo depuis votre appareil ou le web.">

        <ImageImporter onImageLoaded={handleImageLoaded} currentImageUrl={imageDataUrl} />
        {importError && (
          <div className="mt-4 rounded-lg border border-destructive/40 bg-destructive/5 px-4 py-3 font-sans-soft text-sm text-destructive">
            {importError}
          </div>
        )}
        {originalImg && (
          <div className="mt-8 flex justify-center">
            <button
              onClick={() => scrollTo(refFormat)}
              className="rounded-full bg-primary px-8 py-3 font-sans-soft text-base font-medium text-primary-foreground transition-opacity hover:opacity-95 soft-shadow"
            >
              Continuer
            </button>
          </div>
        )}
      </SectionShell>

      {/* ─── FORMAT (compact) ─── */}
      {originalImg && (
        <SectionShell
          ref={refFormat}
          divider
          compact
          title="Définissez le format"
          subtitle="Proportions et dimensions de votre Fragment."
        >
          {/* Shape tabs */}
          <div className="inline-flex rounded-full border border-border bg-card p-1 soft-shadow">
            {(['square', 'landscape', 'portrait'] as Shape[]).map(shape => {
              const active = currentShape === shape;
              return (
                <button
                  key={shape}
                  onClick={() => {
                    const next = FORMAT_OPTIONS.find(o => o.shape === shape);
                    if (next) handleFormatSelect(next);
                  }}
                  className={`rounded-full px-5 py-2 font-sans-soft text-sm transition-colors ${
                    active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
                  }`}
                >
                  {SHAPE_LABEL[shape]}
                </button>
              );
            })}
          </div>

          {/* Size pills */}
          <div className="mt-4 flex flex-wrap gap-2">
            {FORMAT_OPTIONS.filter(o => o.shape === currentShape).map(opt => {
              const active = formatKey(format) === opt.key;
              return (
                <button
                  key={opt.key}
                  onClick={() => handleFormatSelect(opt)}
                  className={`rounded-full border px-5 py-2 font-sans-soft text-sm transition-all ${
                    active
                      ? 'border-accent bg-accent/15 text-foreground'
                      : 'border-border bg-card text-muted-foreground hover:text-foreground hover:border-accent/50'
                  }`}
                >
                  {opt.label}
                </button>
              );
            })}
          </div>

          {/* Viewport picker (compact) */}
          <div className="mt-6">
            <p className="mb-2 font-sans-soft text-xs text-muted-foreground">
              Déplacez l'image pour ajuster le cadrage.
            </p>
            <div className="inline-block max-w-full rounded-2xl border border-border bg-card p-3 soft-shadow">
              <ViewportPicker
                image={originalImg}
                format={format}
                viewport={viewport}
                onViewportChange={setViewport}
                lockedSize={lockedSize}
              />
            </div>
            {pixelArtInfo?.isPixelArt && (
              <div className="mt-3 flex items-center justify-between rounded-xl border border-accent/30 bg-accent/5 px-4 py-2.5">
                <span className="font-sans-soft text-xs text-foreground">
                  {pixelArtLockActive
                    ? 'Image native détectée — cadrage aligné.'
                    : forcePhoto
                      ? 'Traitement photo activé.'
                      : 'Image native détectée.'}
                </span>
                <button
                  onClick={() => setForcePhoto(v => !v)}
                  className="font-sans-soft text-xs underline text-muted-foreground hover:text-foreground"
                >
                  {forcePhoto ? 'Réactiver alignement' : 'Traiter comme photo'}
                </button>
              </div>
            )}
          </div>

          <div className="mt-6 flex justify-center">
            <button
              onClick={() => scrollTo(refDetail)}
              className="rounded-full bg-primary px-7 py-2.5 font-sans-soft text-sm font-medium text-primary-foreground transition-opacity hover:opacity-95 soft-shadow"
            >
              Valider le format
            </button>
          </div>
        </SectionShell>
      )}

      {/* ─── DETAIL — with real previews ─── */}
      {originalImg && (
        <SectionShell
          ref={refDetail}
          divider
          compact
          title="Définissez le niveau de détail"
          subtitle="Chaque pixel est un fragment de bois — choisissez sa taille."
        >
          <div className="grid gap-3 sm:grid-cols-2">
            {(['detailed', 'balanced'] as Detail[]).map(d => {
              const active = currentDetail === d;
              const isDetailed = d === 'detailed';
              return (
                <button
                  key={d}
                  type="button"
                  onClick={() => handleDetailSelect(d)}
                  className={`group relative flex flex-col items-center gap-4 rounded-2xl border bg-card p-5 text-center transition-all duration-300
                    ${active
                      ? 'border-accent ring-2 ring-accent/30 soft-shadow-lg'
                      : 'border-border hover:border-accent/60 soft-shadow'}`}
                >
                  <div className="flex w-full items-center justify-center rounded-lg bg-muted/40 p-3">
                    <DetailPreview
                      sourceImageData={sourceImageData}
                      viewport={viewport}
                      widthCm={tableSizeCm.w}
                      heightCm={tableSizeCm.h}
                      pixelSizeMm={DETAIL_MM[d]}
                      maxHeightPx={200}
                    />
                  </div>
                  <div className="min-w-0 flex-1">
                    <h3 className="font-serif text-lg sm:text-xl text-foreground leading-tight">
                      {isDetailed ? 'Détaillé' : 'Équilibré'}
                    </h3>
                    <p className="mt-1 font-sans-soft text-xs sm:text-sm leading-relaxed text-muted-foreground">
                      Pixels de {DETAIL_MM[d]} mm — {isDetailed ? 'un rendu précis et nuancé.' : 'un rendu plus graphique.'}
                    </p>
                  </div>
                  {active && (
                    <span className="absolute right-3 top-3 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent text-accent-foreground text-xs">✓</span>
                  )}
                </button>
              );
            })}
          </div>
          <div className="mt-6 flex justify-center">
            <button
              onClick={() => scrollTo(refCollection)}
              className="rounded-full bg-primary px-7 py-2.5 font-sans-soft text-sm font-medium text-primary-foreground transition-opacity hover:opacity-95 soft-shadow"
            >
              Valider le niveau de détail
            </button>
          </div>

        </SectionShell>
      )}

      {/* ─── COLLECTION ─── */}
      {originalImg && (
        <SectionShell
          ref={refCollection}
          divider
          compact
          title="Sélectionnez une collection de bois"
          subtitle="Chaque collection apporte une identité unique à votre Fragment."
        >
          <div className="grid gap-4 sm:grid-cols-2">
            <ChoiceCard
              title="Bois naturels français"
              description="Des essences lumineuses et équilibrées inspirées de l'artisanat français."
              active={suggestionMode === 'filtered'}
              onSelect={() => handleCollectionSelect('filtered')}
              preview={<CollectionPreview textures={collectionPreviews.filtered} />}
            />
            <ChoiceCard
              title="Collection étendue"
              description="Une sélection plus large d'essences et de contrastes."
              active={suggestionMode === 'all'}
              onSelect={() => handleCollectionSelect('all')}
              preview={<CollectionPreview textures={collectionPreviews.all} />}
            />
          </div>
          <div className="mt-6 flex justify-center">
            <button
              onClick={() => scrollTo(refRefine)}
              className="rounded-full bg-primary px-7 py-2.5 font-sans-soft text-sm font-medium text-primary-foreground transition-opacity hover:opacity-95 soft-shadow"
            >
              Valider la collection
            </button>
          </div>
        </SectionShell>
      )}


      {/* ─── REFINE (discrète, fermée par défaut) ─── */}
      {originalImg && (
        <section ref={refRefine} className="relative w-full px-5 sm:px-8 py-10 sm:py-14 fade-in-up">
          <div className="mx-auto max-w-3xl lg:mx-0 lg:max-w-none">
            <div className="mb-10 sm:mb-14 h-px w-16 bg-border" aria-hidden />
            <button
              onClick={() => setRefineOpen(o => !o)}
              className="flex w-full items-center justify-between rounded-2xl border border-border bg-card px-5 sm:px-6 py-5 text-left soft-shadow transition-colors hover:bg-muted/40"
            >
              <span className="font-serif text-2xl sm:text-3xl text-foreground">
                Affinez votre Fragment
              </span>
              {refineOpen ? <ChevronUp size={22} /> : <ChevronDown size={22} />}
            </button>

            {refineOpen && (
              <div className="mt-6 space-y-6">
                {/* ── Palette active ── */}
                <div className="rounded-2xl border border-border bg-card p-5 sm:p-6 soft-shadow">
                  <p className="font-sans-soft text-sm text-foreground leading-relaxed">
                    Voici la palette d'essence de bois utilisée pour composer votre image. Vous pouvez ajouter ou retirer des essences de bois.
                  </p>
                  <div className="mt-5">
                    <PaletteEditor palette={palette} onChange={handleManualPaletteChange} />
                  </div>
                  <button
                    onClick={() => setLibraryOpen(true)}
                    className="mt-5 inline-flex items-center gap-2 rounded-full border border-border bg-card px-5 py-2.5 font-sans-soft text-sm text-foreground hover:bg-muted"
                  >
                    <Palette size={14} /> Parcourir la bibliothèque
                  </button>
                </div>

                {/* ── Retouche manuelle ── */}
                <div className="rounded-2xl border border-border bg-card p-5 sm:p-6 soft-shadow">
                  <p className="font-sans-soft text-sm text-foreground leading-relaxed">
                    Vous pouvez retoucher votre composition pixel par pixel.
                  </p>
                  <div className="mt-4 flex flex-wrap gap-2">
                    <button
                      onClick={() => {
                        setEditMode(m => {
                          const next = !m;
                          if (next && !selectedEditColor && palette[0]) setSelectedEditColor(palette[0].hex);
                          return next;
                        });
                      }}
                      className={`rounded-full px-5 py-2 font-sans-soft text-sm transition-colors ${
                        editMode ? 'bg-accent text-accent-foreground' : 'bg-secondary text-foreground hover:bg-muted'
                      }`}
                    >
                      {editMode ? 'Retouche manuelle activée' : 'Retouche manuelle'}
                    </button>
                    {hasManualEdits && (
                      <button
                        onClick={handleResetEdits}
                        className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-4 py-2 font-sans-soft text-sm text-muted-foreground hover:text-foreground"
                      >
                        <RotateCcw size={14} /> Réinitialiser
                      </button>
                    )}
                  </div>

                  {editMode && mappedGrid && (
                    <div className="mt-6 space-y-5">
                      {/* Live preview */}
                      <div className="overflow-hidden rounded-xl border border-border bg-background p-3">
                        <PixelGrid
                          mappedGrid={mappedGrid}
                          palette={palette}
                          pixelSizeCm={pixelSizeCm}
                          editMode={editMode}
                          onEditPixel={handleEditPixel}
                        />
                      </div>

                      {/* Library grouped by tag — priority: French + Naturelle */}
                      <RetouchLibrary
                        selectedHex={selectedEditColor}
                        onSelect={(entry) => {
                          // Ensure picked essence is in palette so texture + symbol render correctly
                          if (!palette.some(c => c.hex.toLowerCase() === entry.hex.toLowerCase())) {
                            const symbols = assignSymbolsToPalette([...palette.map(c => c.hex), entry.hex]);
                            const next: PaletteColor[] = [
                              ...palette.map((c, i) => ({ ...c, symbol: symbols[i] })),
                              {
                                hex: entry.hex,
                                symbol: symbols[symbols.length - 1],
                                name: entry.name,
                                textureUrl: entry.textureUrl,
                                textureAvgColor: entry.hex,
                              },
                            ];
                            handleManualPaletteChange(next);
                          }
                          setSelectedEditColor(entry.hex);
                        }}
                        libraryTick={libraryTick}
                      />

                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        </section>
      )}

      {/* ─── VALIDATION ─── */}
      {originalImg && gridColors.length > 0 && (
        <SectionShell
          ref={refValidation}
          divider
          title="Votre Fragment est prêt"
          subtitle="Vérifiez votre composition avant validation."
        >
          <div className="grid gap-6 sm:grid-cols-[1fr_280px]">
            {/* Récap */}
            <div className="rounded-2xl border border-border bg-card p-6 soft-shadow">
              <dl className="space-y-4">
                {[
                  ['Format', currentShape === 'square' ? 'Carré' : currentShape === 'landscape' ? 'Paysage' : 'Portrait'],
                  ['Niveau de détail', currentDetail === 'detailed' ? 'Détaillé' : 'Équilibré'],
                  ['Collection', suggestionMode === 'filtered' ? 'Bois naturels français' : suggestionMode === 'all' ? 'Collection étendue' : 'Personnalisée'],
                  ['Dimensions', `${tableSizeCm.w} × ${tableSizeCm.h} cm`],
                  ['Essences utilisées', `${palette.length}`],
                ].map(([k, v]) => (
                  <div key={k} className="flex items-center justify-between border-b border-border/60 pb-3 last:border-0 last:pb-0">
                    <dt className="font-sans-soft text-sm text-muted-foreground">{k}</dt>
                    <dd className="font-serif text-lg text-foreground">{v}</dd>
                  </div>
                ))}
              </dl>
            </div>
            {/* Price + CTAs */}
            <div className="flex flex-col gap-3">
              <div className="rounded-2xl border border-border bg-card p-6 soft-shadow text-center">
                <div className="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">Prix estimé</div>
                <div className="mt-2 font-serif text-4xl text-foreground">{priceEstimate} €</div>
                <div className="mt-1 font-sans-soft text-xs text-muted-foreground">Pièce unique sur mesure</div>
              </div>
              <button
                onClick={handleAddToCart}
                className="rounded-full bg-primary px-6 py-4 font-sans-soft text-base font-medium text-primary-foreground transition-opacity hover:opacity-95 soft-shadow-lg"
              >
                Finaliser mon Fragment
              </button>
              <button
                onClick={() => scrollTo(refFormat)}
                className="rounded-full border border-border bg-card px-6 py-3 font-sans-soft text-sm text-foreground hover:bg-muted"
              >
                Modifier la composition
              </button>
            </div>
          </div>
        </SectionShell>
      )}

      {/* Bottom breathing room so floating preview never covers the last content */}
      <div className="h-32 lg:h-12" />

      </div>{/* end workflow wrapper */}

      {/* ─── Desktop side preview (40vw, centered in right 50vw, only past hero) ─── */}
      {gridColors.length > 0 && mappedGrid && pastHero && (
        <aside
          aria-label="Aperçu de votre Fragment"
          className="hidden lg:flex fixed z-30 flex-col rounded-2xl border border-border bg-card soft-shadow-lg overflow-hidden"
          style={{ right: '5vw', width: '40vw', top: 'calc(50vh + 32px)', transform: 'translateY(-50%)', maxHeight: 'calc(100vh - 80px)' }}
        >
          <div className="flex-1 overflow-y-auto p-4">
            <PixelGrid
              mappedGrid={mappedGrid}
              palette={palette}
              pixelSizeCm={pixelSizeCm}
              editMode={editMode}
              onEditPixel={handleEditPixel}
            />
          </div>
        </aside>
      )}

      {/* ─── Floating live preview — mobile & tablet only ─── */}
      <div className="lg:hidden">
        <FloatingPreview
          visible={gridColors.length > 0 && !!mappedGrid}
          thumb={previewThumb}
          label={previewLabel}
        >
          {mappedGrid && (
            <PixelGrid
              mappedGrid={mappedGrid}
              palette={palette}
              canvasRef={canvasRef}
              pixelSizeCm={pixelSizeCm}
              editMode={editMode}
              onEditPixel={handleEditPixel}
            />
          )}
        </FloatingPreview>
      </div>

      <LibraryBrowserModal
        open={libraryOpen}
        onOpenChange={setLibraryOpen}
        palette={palette}
        onPaletteChange={handleManualPaletteChange}
        mappedGrid={mappedGrid}
        pixelSizeCm={pixelSizeCm}
        libraryTick={libraryTick}
        onAddEntry={(entry: LibraryEntry) => {
          if (palette.some(c => c.hex.toLowerCase() === entry.hex.toLowerCase())) return;
          const used = new Set(palette.map(c => c.symbol));
          const sym = getSymbolForHex(entry.hex, used);
          const newColor: PaletteColor = {
            hex: entry.hex, symbol: sym, name: entry.name,
            textureUrl: entry.textureUrl, textureAvgColor: entry.hex,
          };
          handleManualPaletteChange([...palette, newColor]);
        }}
      />
    </div>
  );
}

