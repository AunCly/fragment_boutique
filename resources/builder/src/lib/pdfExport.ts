import jsPDF from 'jspdf';
import { PaletteColor, hexToRgb } from './pixelArt';
import { MappedGrid } from './pipeline';

/** Load all texture images for the palette. */
async function loadTextures(palette: PaletteColor[]): Promise<Map<string, HTMLImageElement>> {
  const map = new Map<string, HTMLImageElement>();
  await Promise.all(
    palette
      .filter(c => c.textureUrl)
      .map(
        c =>
          new Promise<void>(resolve => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => {
              map.set(c.hex, img);
              resolve();
            };
            img.onerror = () => resolve();
            img.src = c.textureUrl!;
          }),
      ),
  );
  return map;
}

type RenderMode = 'texture' | 'grid' | 'combined';

/** Render the mapped grid into an offscreen canvas at high resolution. */
function renderCanvas(
  mappedGrid: MappedGrid,
  textures: Map<string, HTMLImageElement>,
  mode: RenderMode,
  cellPx = 32,
): HTMLCanvasElement {
  const { mapped, cols, rows } = mappedGrid;
  const canvas = document.createElement('canvas');
  canvas.width = cols * cellPx;
  canvas.height = rows * cellPx;
  const ctx = canvas.getContext('2d')!;

  // Background
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  for (let row = 0; row < rows; row++) {
    for (let col = 0; col < cols; col++) {
      const matched = mapped[row]?.[col];
      if (!matched) continue;
      const x = col * cellPx;
      const y = row * cellPx;

      if (mode === 'texture' || mode === 'combined') {
        const tex = textures.get(matched.hex);
        if (tex) {
          ctx.drawImage(tex, 0, 0, tex.width, tex.height, x, y, cellPx, cellPx);
        } else {
          ctx.fillStyle = matched.textureAvgColor || matched.hex;
          ctx.fillRect(x, y, cellPx, cellPx);
        }
      }

      if (mode === 'grid') {
        // Transparent-style: white background already drawn. Just draw symbol.
        ctx.strokeStyle = '#888';
        ctx.lineWidth = 1;
        ctx.strokeRect(x + 0.5, y + 0.5, cellPx - 1, cellPx - 1);
        ctx.fillStyle = '#1a1a1f';
        ctx.font = `bold ${Math.floor(cellPx * 0.5)}px "JetBrains Mono", monospace`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(matched.symbol, x + cellPx / 2, y + cellPx / 2);
      }
    }
  }

  // Grid overlay + symbols for combined mode
  if (mode === 'combined') {
    ctx.strokeStyle = 'rgba(0,0,0,0.55)';
    ctx.lineWidth = 1;
    for (let c = 0; c <= cols; c++) {
      const x = c * cellPx + 0.5;
      ctx.beginPath();
      ctx.moveTo(x, 0);
      ctx.lineTo(x, canvas.height);
      ctx.stroke();
    }
    for (let r = 0; r <= rows; r++) {
      const y = r * cellPx + 0.5;
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(canvas.width, y);
      ctx.stroke();
    }

    // Symbol overlay — readable on any wood texture (white text + dark stroke)
    const fontSize = Math.floor(cellPx * 0.5);
    ctx.font = `bold ${fontSize}px "JetBrains Mono", monospace`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.lineWidth = Math.max(2, cellPx * 0.08);
    ctx.lineJoin = 'round';
    for (let row = 0; row < rows; row++) {
      for (let col = 0; col < cols; col++) {
        const matched = mapped[row]?.[col];
        if (!matched) continue;
        const cx = col * cellPx + cellPx / 2;
        const cy = row * cellPx + cellPx / 2;
        ctx.strokeStyle = 'rgba(0,0,0,0.85)';
        ctx.strokeText(matched.symbol, cx, cy);
        ctx.fillStyle = '#ffffff';
        ctx.fillText(matched.symbol, cx, cy);
      }
    }
  }

  return canvas;
}

/** Add a centered image page with a title. */
function addImagePage(
  pdf: jsPDF,
  title: string,
  canvas: HTMLCanvasElement,
  isFirst: boolean,
) {
  if (!isFirst) pdf.addPage();
  const pageW = pdf.internal.pageSize.getWidth();
  const pageH = pdf.internal.pageSize.getHeight();
  const margin = 15;

  // Title
  pdf.setFont('helvetica', 'bold');
  pdf.setFontSize(16);
  pdf.setTextColor(20, 20, 25);
  pdf.text(title, pageW / 2, margin + 4, { align: 'center' });

  // Image area
  const availW = pageW - margin * 2;
  const availH = pageH - margin * 2 - 12;
  const ratio = canvas.width / canvas.height;
  let drawW = availW;
  let drawH = drawW / ratio;
  if (drawH > availH) {
    drawH = availH;
    drawW = drawH * ratio;
  }
  const x = (pageW - drawW) / 2;
  const y = margin + 12 + (availH - drawH) / 2;
  const dataUrl = canvas.toDataURL('image/png');
  pdf.addImage(dataUrl, 'PNG', x, y, drawW, drawH);
}

interface ColorRow {
  color: PaletteColor;
  count: number;
  pct: number;
}

function computeStats(mappedGrid: MappedGrid): { rows: ColorRow[]; total: number } {
  const counts = new Map<string, { color: PaletteColor; count: number }>();
  for (const row of mappedGrid.mapped) {
    for (const cell of row) {
      const e = counts.get(cell.hex);
      if (e) e.count++;
      else counts.set(cell.hex, { color: cell, count: 1 });
    }
  }
  const total = mappedGrid.cols * mappedGrid.rows;
  const rows = [...counts.values()]
    .sort((a, b) => b.count - a.count)
    .map(({ color, count }) => ({ color, count, pct: (count / total) * 100 }));
  return { rows, total };
}

function addStatsPage(
  pdf: jsPDF,
  mappedGrid: MappedGrid,
  pixelSizeCm: number,
  textures: Map<string, HTMLImageElement>,
) {
  pdf.addPage();
  const pageW = pdf.internal.pageSize.getWidth();
  const margin = 15;

  pdf.setFont('helvetica', 'bold');
  pdf.setFontSize(16);
  pdf.setTextColor(20, 20, 25);
  pdf.text('Quantité de bois', pageW / 2, margin + 4, { align: 'center' });

  // Subtitle: dimensions
  const { rows, total } = computeStats(mappedGrid);
  const widthCm = mappedGrid.cols * pixelSizeCm;
  const heightCm = mappedGrid.rows * pixelSizeCm;
  pdf.setFont('helvetica', 'normal');
  pdf.setFontSize(10);
  pdf.setTextColor(100, 100, 110);
  pdf.text(
    `Grille ${mappedGrid.cols} × ${mappedGrid.rows} pixels  ·  ${widthCm} × ${heightCm} cm  ·  ${total} pixels au total`,
    pageW / 2,
    margin + 11,
    { align: 'center' },
  );

  // Table header
  let y = margin + 22;
  const cols = {
    swatch: margin,
    name: margin + 14,
    hex: margin + 80,
    symbol: margin + 110,
    count: margin + 130,
    pct: margin + 155,
  };
  pdf.setFont('helvetica', 'bold');
  pdf.setFontSize(9);
  pdf.setTextColor(60, 60, 70);
  pdf.text('Couleur', cols.name, y);
  pdf.text('HEX', cols.hex, y);
  pdf.text('Sym.', cols.symbol, y);
  pdf.text('Pixels', cols.count, y);
  pdf.text('%', cols.pct, y);
  y += 2;
  pdf.setDrawColor(180, 180, 190);
  pdf.setLineWidth(0.3);
  pdf.line(margin, y, pageW - margin, y);
  y += 4;

  pdf.setFont('helvetica', 'normal');
  pdf.setFontSize(10);
  const lineH = 8;
  const pageH = pdf.internal.pageSize.getHeight();

  for (const { color, count, pct } of rows) {
    if (y > pageH - margin) {
      pdf.addPage();
      y = margin;
    }
    // Swatch
    const tex = textures.get(color.hex);
    if (tex) {
      // Render small thumbnail to dataURL
      const thumb = document.createElement('canvas');
      thumb.width = 32;
      thumb.height = 32;
      thumb.getContext('2d')!.drawImage(tex, 0, 0, tex.width, tex.height, 0, 0, 32, 32);
      pdf.addImage(thumb.toDataURL('image/png'), 'PNG', cols.swatch, y - 4, 8, 8);
    } else {
      const [r, g, b] = hexToRgb(color.textureAvgColor || color.hex);
      pdf.setFillColor(r, g, b);
      pdf.rect(cols.swatch, y - 4, 8, 8, 'F');
    }
    pdf.setDrawColor(150, 150, 160);
    pdf.setLineWidth(0.2);
    pdf.rect(cols.swatch, y - 4, 8, 8);

    pdf.setTextColor(20, 20, 25);
    const name = color.name || '—';
    pdf.text(name.length > 32 ? name.slice(0, 30) + '…' : name, cols.name, y);
    pdf.setTextColor(100, 100, 110);
    pdf.text(color.hex.toUpperCase(), cols.hex, y);
    pdf.setTextColor(20, 20, 25);
    pdf.setFont('helvetica', 'bold');
    pdf.text(color.symbol, cols.symbol, y);
    pdf.setFont('helvetica', 'normal');
    pdf.text(String(count), cols.count, y);
    pdf.text(pct.toFixed(2) + ' %', cols.pct, y);

    y += lineH;
  }
}

export async function exportPdf(
  mappedGrid: MappedGrid,
  palette: PaletteColor[],
  pixelSizeCm: number,
) {
  const textures = await loadTextures(palette);

  const cellPx = Math.max(16, Math.min(48, Math.floor(2400 / Math.max(mappedGrid.cols, mappedGrid.rows))));
  const texCanvas = renderCanvas(mappedGrid, textures, 'texture', cellPx);
  const gridCanvas = renderCanvas(mappedGrid, textures, 'grid', cellPx);
  const combinedCanvas = renderCanvas(mappedGrid, textures, 'combined', cellPx);

  // Use orientation matching grid
  const isLandscape = mappedGrid.cols > mappedGrid.rows;
  const pdf = new jsPDF({
    orientation: isLandscape ? 'landscape' : 'portrait',
    unit: 'mm',
    format: 'a4',
  });

  addImagePage(pdf, 'Texture bois', texCanvas, true);
  addImagePage(pdf, 'Grille', gridCanvas, false);
  addImagePage(pdf, 'Texture bois + Grille', combinedCanvas, false);
  addStatsPage(pdf, mappedGrid, pixelSizeCm, textures);

  pdf.save(`pixel-grid-${mappedGrid.cols}x${mappedGrid.rows}.pdf`);
}
