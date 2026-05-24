import { useState, useRef } from 'react';
import { LibraryEntry, SavedPalette, loadLibrary, addToLibrary, removeFromLibrary, updateLibraryEntry, blobUrlToDataUrl, getAllTags, getTagsByCategory, loadPalettes, addPalette, removePalette } from '@/lib/colorLibrary';
import { DEFAULT_FILTER_TAGS } from '@/lib/paletteSuggestion';

/**
 * Priority score for an entry in the library list.
 * 0 = PRIMARY: natural French wood palette (existing tag-based system)
 * 1 = SECONDARY: any other tagged wood
 * 2 = FALLBACK: untagged / hex-only entries
 * Texture availability is unaffected — texture_url still overrides hex at render time.
 */
function entryPriority(e: LibraryEntry): number {
  if (DEFAULT_FILTER_TAGS.every(t => e.tags.includes(t))) return 0;
  if (e.tags.length > 0) return 1;
  return 2;
}
import { PaletteColor } from '@/lib/pixelArt';
import { Plus, Trash2, ImagePlus, X, Edit2, Check, Save, FolderOpen } from 'lucide-react';
import { Switch } from '@/components/ui/switch';
import { toast } from 'sonner';

interface Props {
  palette: PaletteColor[];
  onPick: (entry: LibraryEntry) => void;
  onLoadPalette?: (colors: { hex: string; name: string; textureUrl?: string }[]) => void;
}

function computeAverageColor(img: HTMLImageElement): string {
  const canvas = document.createElement('canvas');
  canvas.width = img.width;
  canvas.height = img.height;
  const ctx = canvas.getContext('2d')!;
  ctx.drawImage(img, 0, 0);
  const data = ctx.getImageData(0, 0, img.width, img.height).data;
  let r = 0, g = 0, b = 0, count = 0;
  for (let i = 0; i < data.length; i += 4) {
    r += data[i]; g += data[i + 1]; b += data[i + 2]; count++;
  }
  r = Math.round(r / count); g = Math.round(g / count); b = Math.round(b / count);
  return '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('');
}

export default function ColorLibraryManager({ palette, onPick, onLoadPalette }: Props) {
  const [library, setLibrary] = useState<LibraryEntry[]>(loadLibrary);
  const [isAdding, setIsAdding] = useState(false);
  const [newName, setNewName] = useState('');
  const [newHex, setNewHex] = useState('#888888');
  const [newTexturePreview, setNewTexturePreview] = useState<string | null>(null);
  const [newTags, setNewTags] = useState<string[]>([]);
  const [newTagInput, setNewTagInput] = useState('');
  const fileRef = useRef<HTMLInputElement>(null);
  const editFileRef = useRef<HTMLInputElement>(null);

  const [editingId, setEditingId] = useState<string | null>(null);
  const [editTagInput, setEditTagInput] = useState('');
  const [filterTags, setFilterTags] = useState<string[]>([]);
  const [showUsed, setShowUsed] = useState(false);

  // Palette save/load
  const [savedPalettes, setSavedPalettes] = useState<SavedPalette[]>(loadPalettes);
  const [savePaletteName, setSavePaletteName] = useState('');
  const [showSavePalette, setShowSavePalette] = useState(false);

  const allTags = getAllTags();
  const tagCategories = getTagsByCategory();
  const paletteHexes = new Set(palette.map(c => c.hex));

  const refresh = () => setLibrary(loadLibrary());

  const handleAddTexture = (file: File) => {
    if (!file.type.startsWith('image/')) {
      toast.error('Format non supporté. Utilisez PNG ou JPG.');
      return;
    }
    const url = URL.createObjectURL(file);
    setNewTexturePreview(url);
    const img = new Image();
    img.onload = () => {
      setNewHex(computeAverageColor(img));
      toast.success('Texture chargée');
    };
    img.onerror = () => {
      toast.error('Impossible de charger l\'image');
      URL.revokeObjectURL(url);
      setNewTexturePreview(null);
    };
    img.src = url;
  };

  const handleSave = async () => {
    try {
      let textureDataUrl: string | undefined;
      if (newTexturePreview) {
        textureDataUrl = await blobUrlToDataUrl(newTexturePreview);
      }
      addToLibrary({
        name: newName || newHex,
        hex: newHex,
        textureUrl: textureDataUrl,
        tags: newTags,
      });
      setIsAdding(false);
      setNewName('');
      setNewHex('#888888');
      setNewTags([]);
      setNewTagInput('');
      if (newTexturePreview) URL.revokeObjectURL(newTexturePreview);
      setNewTexturePreview(null);
      refresh();
      toast.success('Couleur enregistrée');
    } catch (err) {
      toast.error('Erreur lors de l\'enregistrement');
      console.error('Save error:', err);
    }
  };

  const handleRemove = (id: string) => {
    removeFromLibrary(id);
    if (editingId === id) setEditingId(null);
    refresh();
  };

  const handleEditTexture = async (id: string, file: File) => {
    if (!file.type.startsWith('image/')) {
      toast.error('Format non supporté. Utilisez PNG ou JPG.');
      return;
    }
    try {
      const url = URL.createObjectURL(file);
      const dataUrl = await blobUrlToDataUrl(url);
      URL.revokeObjectURL(url);
      const img = new Image();
      img.onload = () => {
        const avg = computeAverageColor(img);
        updateLibraryEntry(id, { textureUrl: dataUrl, hex: avg });
        refresh();
        toast.success('Texture mise à jour');
      };
      img.onerror = () => toast.error('Impossible de charger la texture');
      img.src = dataUrl;
    } catch (err) {
      toast.error('Erreur lors du chargement de la texture');
      console.error('Edit texture error:', err);
    }
  };

  const handleRemoveTexture = (id: string) => {
    updateLibraryEntry(id, { textureUrl: undefined });
    refresh();
  };

  const handleAddTag = (id: string, tag: string) => {
    const entry = library.find(e => e.id === id);
    if (!entry || !tag.trim()) return;
    const tags = [...new Set([...entry.tags, tag.trim()])];
    updateLibraryEntry(id, { tags });
    refresh();
  };

  const handleRemoveTag = (id: string, tag: string) => {
    const entry = library.find(e => e.id === id);
    if (!entry) return;
    updateLibraryEntry(id, { tags: entry.tags.filter(t => t !== tag) });
    refresh();
  };

  let filtered = filterTags.length > 0
    ? library.filter(e => filterTags.every(t => e.tags.includes(t)))
    : library;
  if (!showUsed) {
    filtered = filtered.filter(e => !paletteHexes.has(e.hex));
  }
  // Priority ordering: natural French palette first, then other tagged, then fallback.
  // Stable sort preserves the existing intra-group order (DB sort_order / insertion).
  filtered = [...filtered].sort((a, b) => entryPriority(a) - entryPriority(b));

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="font-display text-sm font-semibold uppercase tracking-widest text-muted-foreground">
          Bibliothèque
        </h2>
        <button
          onClick={() => setIsAdding(!isAdding)}
          className="flex items-center gap-1 rounded bg-secondary px-2 py-1 text-xs font-mono text-secondary-foreground hover:bg-accent transition-colors"
        >
          <Plus size={12} /> Nouveau
        </button>
      </div>

      {/* Palette save/load */}
      <div className="space-y-2 rounded bg-muted/50 p-2 border border-border">
        <div className="flex items-center justify-between">
          <span className="font-mono text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Palettes enregistrées</span>
          <button
            onClick={() => setShowSavePalette(!showSavePalette)}
            className="flex items-center gap-1 rounded bg-secondary px-2 py-0.5 text-[10px] font-mono text-secondary-foreground hover:bg-accent transition-colors"
          >
            <Save size={10} /> Sauvegarder
          </button>
        </div>
        {showSavePalette && (
          <div className="flex gap-1">
            <input
              type="text"
              placeholder="Nom de la palette..."
              value={savePaletteName}
              onChange={(e) => setSavePaletteName(e.target.value)}
              className="flex-1 rounded bg-input px-2 py-1 font-mono text-[10px] text-foreground border border-border"
            />
            <button
              onClick={() => {
                if (!savePaletteName.trim() || palette.length === 0) return;
                addPalette(savePaletteName.trim(), palette.map(c => ({ hex: c.hex, name: c.name || c.hex, textureUrl: c.textureUrl })));
                setSavedPalettes(loadPalettes());
                setSavePaletteName('');
                setShowSavePalette(false);
              }}
              className="rounded bg-primary px-2 py-1 text-[10px] font-mono text-primary-foreground hover:opacity-90"
            >
              <Check size={10} />
            </button>
          </div>
        )}
        {savedPalettes.length > 0 ? (
          <div className="space-y-1">
            {savedPalettes.map(sp => (
              <div key={sp.id} className="flex items-center gap-2 rounded bg-secondary p-1.5 group">
                <div className="flex gap-0.5 flex-shrink-0">
                  {sp.colors.slice(0, 6).map((c, i) => (
                    <div
                      key={i}
                      className="h-4 w-4 rounded-sm border border-border overflow-hidden"
                    >
                      {c.textureUrl ? (
                        <img src={c.textureUrl} alt="" className="h-full w-full object-cover" />
                      ) : (
                        <div className="h-full w-full" style={{ backgroundColor: c.hex }} />
                      )}
                    </div>
                  ))}
                  {sp.colors.length > 6 && (
                    <span className="font-mono text-[8px] text-muted-foreground self-center">+{sp.colors.length - 6}</span>
                  )}
                </div>
                <span className="font-mono text-[10px] text-foreground flex-1 truncate">{sp.name}</span>
                <button
                  onClick={() => onLoadPalette?.(sp.colors)}
                  className="text-primary opacity-0 group-hover:opacity-100 transition-opacity"
                  title="Charger cette palette"
                >
                  <FolderOpen size={12} />
                </button>
                <button
                  onClick={() => { removePalette(sp.id); setSavedPalettes(loadPalettes()); }}
                  className="text-destructive opacity-0 group-hover:opacity-100 transition-opacity"
                >
                  <Trash2 size={12} />
                </button>
              </div>
            ))}
          </div>
        ) : (
          <p className="font-mono text-[8px] text-muted-foreground">Aucune palette. Ajoutez des couleurs puis sauvegardez.</p>
        )}
      </div>

      <p className="font-mono text-[10px] text-muted-foreground leading-tight">
        💾 Sauvegardé dans le navigateur (localStorage).
      </p>

      {/* Show used toggle */}
      <div className="flex items-center justify-between">
        <span className="font-mono text-[10px] text-muted-foreground">Afficher les couleurs utilisées</span>
        <Switch checked={showUsed} onCheckedChange={setShowUsed} />
      </div>

      {/* Tag filter by category — cumulative AND logic */}
      {tagCategories.length > 0 && (
        <div className="space-y-2">
          <div className="flex items-center justify-between gap-2">
            <span className="font-mono text-[9px] font-semibold text-muted-foreground uppercase tracking-wider">
              Filtres {filterTags.length > 0 && `(${filterTags.length})`}
            </span>
            <button
              onClick={() => setFilterTags([])}
              className={`rounded-full px-2 py-0.5 font-mono text-[10px] transition-colors ${
                filterTags.length === 0 ? 'bg-primary text-primary-foreground' : 'bg-secondary text-muted-foreground hover:bg-accent'
              }`}
            >
              {filterTags.length === 0 ? 'Tout' : 'Réinitialiser'}
            </button>
          </div>

          {/* Active tags chips with quick removal */}
          {filterTags.length > 0 && (
            <div className="flex flex-wrap gap-1 rounded bg-muted/50 p-1.5 border border-border">
              {filterTags.map(tag => (
                <button
                  key={tag}
                  onClick={() => setFilterTags(prev => prev.filter(t => t !== tag))}
                  className="inline-flex items-center gap-1 rounded-full bg-primary px-2 py-0.5 font-mono text-[10px] text-primary-foreground hover:opacity-80 transition-opacity"
                  title="Retirer ce filtre"
                >
                  {tag} <X size={10} />
                </button>
              ))}
            </div>
          )}

          {tagCategories.map(cat => (
            <div key={cat.label} className="space-y-1">
              <span className="font-mono text-[9px] font-semibold text-muted-foreground uppercase tracking-wider">{cat.label}</span>
              <div className="flex flex-wrap gap-1">
                {cat.tags.map(tag => (
                  <button
                    key={tag}
                    onClick={() => setFilterTags(prev =>
                      prev.includes(tag) ? prev.filter(t => t !== tag) : [...prev, tag]
                    )}
                    className={`rounded-full px-2 py-0.5 font-mono text-[10px] transition-colors ${
                      filterTags.includes(tag) ? 'bg-primary text-primary-foreground' : 'bg-secondary text-muted-foreground hover:bg-accent'
                    }`}
                  >
                    {tag}
                  </button>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {isAdding && (
        <div className="rounded bg-muted p-3 space-y-2 border border-border">
          <input
            type="text"
            placeholder="Nom (ex: Chêne clair)"
            value={newName}
            onChange={(e) => setNewName(e.target.value)}
            className="w-full rounded bg-input px-2 py-1 font-mono text-xs text-foreground border border-border"
          />
          <div className="flex items-center gap-2">
            <input
              type="color"
              value={newHex}
              onChange={(e) => setNewHex(e.target.value)}
              className="h-8 w-8 cursor-pointer rounded-sm border-0 p-0"
            />
            <input
              type="text"
              value={newHex}
              onChange={(e) => setNewHex(e.target.value)}
              className="flex-1 rounded bg-input px-2 py-1 font-mono text-xs text-foreground border border-border"
            />
          </div>
          {newTexturePreview ? (
            <div className="flex items-center gap-2">
              <img src={newTexturePreview} alt="" className="h-10 w-10 rounded border border-border object-cover" />
              <span className="font-mono text-xs text-muted-foreground flex-1">Texture ajoutée</span>
              <button onClick={() => { if (newTexturePreview) URL.revokeObjectURL(newTexturePreview); setNewTexturePreview(null); }} className="text-destructive">
                <X size={14} />
              </button>
            </div>
          ) : (
            <button
              onClick={() => fileRef.current?.click()}
              className="flex w-full items-center justify-center gap-2 rounded border border-dashed border-border bg-secondary px-3 py-2 font-mono text-xs text-muted-foreground hover:border-primary hover:text-foreground transition-colors"
            >
              <ImagePlus size={14} /> Ajouter une texture
            </button>
          )}
          <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={(e) => {
            const f = e.target.files?.[0];
            if (f) handleAddTexture(f);
            e.target.value = '';
          }} />

          {/* Tags for new entry */}
          <div className="space-y-1">
            <span className="font-mono text-xs text-muted-foreground">Tags</span>
            <div className="flex flex-wrap gap-1">
              {newTags.map(t => (
                <span key={t} className="inline-flex items-center gap-1 rounded-full bg-accent px-2 py-0.5 font-mono text-[10px] text-accent-foreground">
                  {t}
                  <button onClick={() => setNewTags(newTags.filter(x => x !== t))}><X size={10} /></button>
                </span>
              ))}
            </div>
            <div className="flex gap-1">
              <input
                type="text"
                placeholder="Ajouter un tag..."
                value={newTagInput}
                onChange={(e) => setNewTagInput(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && newTagInput.trim()) {
                    setNewTags([...new Set([...newTags, newTagInput.trim()])]);
                    setNewTagInput('');
                  }
                }}
                className="flex-1 rounded bg-input px-2 py-1 font-mono text-[10px] text-foreground border border-border"
              />
              {newTagInput.trim() && (
                <button
                  onClick={() => { setNewTags([...new Set([...newTags, newTagInput.trim()])]); setNewTagInput(''); }}
                  className="rounded bg-accent px-2 text-accent-foreground"
                >
                  <Plus size={10} />
                </button>
              )}
            </div>
            {allTags.length > 0 && (
              <div className="flex flex-wrap gap-1 mt-1">
                {allTags.filter(t => !newTags.includes(t)).map(t => (
                  <button
                    key={t}
                    onClick={() => setNewTags([...new Set([...newTags, t])])}
                    className="rounded-full bg-secondary px-2 py-0.5 font-mono text-[10px] text-muted-foreground hover:bg-accent transition-colors"
                  >
                    + {t}
                  </button>
                ))}
              </div>
            )}
          </div>

          <button
            onClick={handleSave}
            className="w-full rounded bg-primary px-3 py-1.5 font-mono text-xs font-medium text-primary-foreground hover:opacity-90 transition-opacity"
          >
            Enregistrer
          </button>
        </div>
      )}

      {filtered.length === 0 && !isAdding && (
        filterTags.length > 0 ? (
          <div className="rounded border border-dashed border-border bg-muted/30 p-3 space-y-2">
            <p className="font-mono text-xs text-foreground">
              Aucune couleur ne correspond à ces filtres
            </p>
            <p className="font-mono text-[10px] text-muted-foreground">
              Essayez de retirer un ou plusieurs tags ou réinitialisez les filtres.
            </p>
            <button
              onClick={() => setFilterTags([])}
              className="rounded bg-primary px-2 py-1 font-mono text-[10px] text-primary-foreground hover:opacity-90 transition-opacity"
            >
              Réinitialiser les filtres
            </button>
          </div>
        ) : (
          <p className="font-mono text-xs text-muted-foreground">
            {library.length > 0 && !showUsed ? 'Toutes les couleurs sont utilisées. Activez le switch ci-dessus pour les voir.' : 'Aucune entrée. Ajoutez des couleurs ou textures.'}
          </p>
        )
      )}

      <input ref={editFileRef} type="file" accept="image/*" className="hidden" onChange={(e) => {
        const f = e.target.files?.[0];
        if (f && editingId) handleEditTexture(editingId, f);
        e.target.value = '';
      }} />

      <div className="space-y-1.5">
        {filtered.map(entry => {
          const isUsed = paletteHexes.has(entry.hex);
          return (
            <div key={entry.id}>
              <div
                className={`group relative flex items-center gap-1.5 rounded p-1.5 transition-colors ${
                  isUsed ? 'bg-primary/10 cursor-default opacity-60' : 'bg-secondary cursor-pointer hover:bg-accent'
                }`}
                onClick={() => { if (!isUsed) { onPick(entry); } }}
                title={isUsed ? 'Déjà dans la palette' : `Cliquer pour utiliser "${entry.name}" dans la palette`}
              >
                <div className="h-7 w-7 rounded-sm border border-border flex-shrink-0 overflow-hidden">
                  {entry.textureUrl ? (
                    <img src={entry.textureUrl} alt="" className="h-full w-full object-cover" />
                  ) : (
                    <div className="h-full w-full" style={{ backgroundColor: entry.hex }} />
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-1">
                    {entry.gridCode && (
                      <span className="rounded-sm bg-primary/20 px-1 font-mono text-[9px] font-bold text-primary">{entry.gridCode}</span>
                    )}
                    <span className="font-mono text-[10px] text-foreground block truncate font-semibold">{entry.name}</span>
                  </div>
                  <span className="font-mono text-[10px] text-muted-foreground">{entry.hex}</span>
                  {entry.tags.length > 0 && (
                    <div className="flex flex-wrap gap-0.5 mt-0.5">
                      {entry.tags.map(t => (
                        <span key={t} className="rounded-full bg-accent/50 px-1.5 py-0 font-mono text-[8px] text-muted-foreground">{t}</span>
                      ))}
                    </div>
                  )}
                </div>
                <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button
                    onClick={(e) => { e.stopPropagation(); setEditingId(editingId === entry.id ? null : entry.id); }}
                    className="text-muted-foreground hover:text-foreground"
                  >
                    <Edit2 size={12} />
                  </button>
                  <button
                    onClick={(e) => { e.stopPropagation(); handleRemove(entry.id); }}
                    className="text-destructive"
                  >
                    <Trash2 size={12} />
                  </button>
                </div>
              </div>

              {/* Edit panel */}
              {editingId === entry.id && (
                <div className="rounded-b bg-muted p-2 space-y-2 border border-t-0 border-border" onClick={(e) => e.stopPropagation()}>
                  <div className="flex items-center gap-2">
                    {entry.textureUrl ? (
                      <>
                        <img src={entry.textureUrl} alt="" className="h-8 w-8 rounded border border-border object-cover" />
                        <span className="font-mono text-[10px] text-muted-foreground flex-1">Texture</span>
                        <button onClick={() => { setEditingId(entry.id); editFileRef.current?.click(); }} className="font-mono text-[10px] text-primary hover:underline">Changer</button>
                        <button onClick={() => handleRemoveTexture(entry.id)} className="text-destructive"><X size={12} /></button>
                      </>
                    ) : (
                      <button
                        onClick={() => { setEditingId(entry.id); editFileRef.current?.click(); }}
                        className="flex w-full items-center justify-center gap-2 rounded border border-dashed border-border bg-secondary px-2 py-1.5 font-mono text-[10px] text-muted-foreground hover:border-primary hover:text-foreground transition-colors"
                      >
                        <ImagePlus size={12} /> Ajouter une texture
                      </button>
                    )}
                  </div>

                  <div className="space-y-1">
                    <div className="flex flex-wrap gap-1">
                      {entry.tags.map(t => (
                        <span key={t} className="inline-flex items-center gap-1 rounded-full bg-accent px-2 py-0.5 font-mono text-[10px] text-accent-foreground">
                          {t}
                          <button onClick={() => handleRemoveTag(entry.id, t)}><X size={10} /></button>
                        </span>
                      ))}
                    </div>
                    <div className="flex gap-1">
                      <input
                        type="text"
                        placeholder="Tag..."
                        value={editingId === entry.id ? editTagInput : ''}
                        onChange={(e) => setEditTagInput(e.target.value)}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter' && editTagInput.trim()) {
                            handleAddTag(entry.id, editTagInput);
                            setEditTagInput('');
                          }
                        }}
                        className="flex-1 rounded bg-input px-2 py-0.5 font-mono text-[10px] text-foreground border border-border"
                      />
                      {editTagInput.trim() && (
                        <button
                          onClick={() => { handleAddTag(entry.id, editTagInput); setEditTagInput(''); }}
                          className="rounded bg-accent px-1.5 text-accent-foreground"
                        >
                          <Check size={10} />
                        </button>
                      )}
                    </div>
                    {allTags.filter(t => !entry.tags.includes(t)).length > 0 && (
                      <div className="flex flex-wrap gap-1">
                        {allTags.filter(t => !entry.tags.includes(t)).map(t => (
                          <button
                            key={t}
                            onClick={() => handleAddTag(entry.id, t)}
                            className="rounded-full bg-secondary px-1.5 py-0 font-mono text-[8px] text-muted-foreground hover:bg-accent transition-colors"
                          >
                            + {t}
                          </button>
                        ))}
                      </div>
                    )}
                  </div>

                  <button
                    onClick={() => setEditingId(null)}
                    className="w-full rounded bg-secondary px-2 py-1 font-mono text-[10px] text-muted-foreground hover:bg-accent transition-colors"
                  >
                    Fermer
                  </button>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
