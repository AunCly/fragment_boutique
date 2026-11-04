@extends('layouts.admin')

@section('title', 'Outil Fragment')

@section('main_class', 'max-w-[1600px]')

@section('content')
@php
    $panel = 'border border-border rounded-xl bg-card';
    $heading = 'font-sans-soft text-[11px] uppercase tracking-widest text-muted-foreground';
    $chip = 'px-2.5 py-1 border border-border font-sans-soft text-xs font-mono hover:bg-muted/60 transition data-[active=true]:bg-foreground data-[active=true]:text-background data-[active=true]:border-foreground';
    $smallButton = 'px-3 py-1.5 border border-border bg-card font-sans-soft text-xs hover:bg-muted/60 transition disabled:opacity-40 disabled:cursor-not-allowed';
    $field = 'w-full px-2.5 py-1.5 bg-background border border-border rounded-lg font-sans-soft text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition';
    $tab = 'flex-1 text-center py-2 border-b-2 border-transparent text-muted-foreground data-[active=true]:border-foreground data-[active=true]:text-foreground transition';
    $segment = 'px-2.5 py-0.5 data-[active=true]:bg-foreground data-[active=true]:text-background';
@endphp

@if ($config['palettes']->isEmpty() || $config['shapes']->isEmpty())
    <h1 class="font-serif text-2xl text-foreground mb-4">Outil Fragment</h1>
    <div class="text-center py-20 border border-border rounded-2xl bg-card">
        <p class="font-sans-soft text-muted-foreground">
            Il faut au moins un format disponible dans l'outil et une palette active d'au moins 2 essences.
        </p>
        <div class="flex justify-center gap-4 mt-4">
            <a href="{{ route('admin.formats.index') }}" class="font-sans-soft text-sm underline">Formats</a>
            <a href="{{ route('admin.palettes.index') }}" class="font-sans-soft text-sm underline">Palettes</a>
        </div>
    </div>
@else
<div id="fragment-tool">
    <script type="application/json" data-ref="config">@json($config)</script>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3 min-w-0">
            <h1 class="font-serif text-2xl text-foreground">Outil Fragment</h1>
            <div data-ref="project-bar" class="hidden flex items-center gap-2 min-w-0">
                <input type="text" data-ref="project-name" maxlength="120" class="w-56 px-2.5 py-1 bg-background border border-border rounded-lg font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition" aria-label="Nom du projet">
                <span data-ref="save-status" class="font-sans-soft text-[11px] text-muted-foreground whitespace-nowrap"></span>
            </div>
        </div>

        {{-- Progress through the flow --}}
        <ol class="flex flex-wrap items-center gap-1 font-sans-soft text-xs">
            @foreach (['Image', 'Générer', 'Choisir', 'Finitions', 'Exporter'] as $index => $step)
                <li class="flex items-center gap-1">
                    <span data-step="{{ $index }}" class="group flex items-center gap-1.5 px-2.5 py-1 rounded-full text-muted-foreground data-[state=done]:text-foreground data-[state=current]:bg-foreground data-[state=current]:text-background">
                        <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] border border-border group-data-[state=done]:border-primary group-data-[state=done]:bg-primary group-data-[state=done]:text-primary-foreground group-data-[state=current]:border-background group-data-[state=current]:bg-background group-data-[state=current]:text-foreground">
                            <span class="group-data-[state=done]:hidden">{{ $index + 1 }}</span><span class="hidden group-data-[state=done]:inline">✓</span>
                        </span>
                        {{ $step }}
                    </span>
                    @unless ($loop->last)
                        <span class="text-border">—</span>
                    @endunless
                </li>
            @endforeach
        </ol>

        <div class="flex items-center gap-3">
            <label class="flex items-center gap-1.5 font-sans-soft text-xs cursor-pointer" title="Aucun appel à Gemini, gratuit">
                <input type="checkbox" data-ref="test-mode" class="peer sr-only" @checked($config['ai']['testModeByDefault'])>
                <span class="relative inline-block w-8 h-4 rounded-full bg-border peer-checked:bg-primary transition after:absolute after:left-0.5 after:top-0.5 after:w-3 after:h-3 after:rounded-full after:bg-card after:transition peer-checked:after:translate-x-4"></span>
                Mode test
            </label>
            <a href="{{ $config['routes']['projects'] }}" class="{{ $smallButton }}">Projets</a>
            <button type="button" class="{{ $smallButton }}" data-action="new-project">Nouveau projet</button>
        </div>
    </div>

    <div data-ref="error" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-xl font-sans-soft text-sm text-red-700"></div>

    <div class="grid gap-4 lg:grid-cols-[270px_minmax(0,1fr)] xl:grid-cols-[270px_minmax(0,1fr)_300px] items-start">

        {{-- ============================================================ Left: source & generation --}}
        <aside class="space-y-3 lg:sticky lg:top-20">
            <section class="{{ $panel }} p-3 space-y-3">
                <div class="space-y-1.5">
                    <p class="{{ $heading }}">Palette</p>
                    <select data-ref="palette" class="{{ $field }}">
                        @foreach ($config['palettes'] as $palette)
                            <option value="{{ $palette['id'] }}">{{ $palette['name'] }} ({{ count($palette['essences']) }})</option>
                        @endforeach
                    </select>
                    <div data-ref="palette-swatches" class="flex flex-wrap gap-1"></div>
                </div>

                <div class="space-y-1.5">
                    <p class="{{ $heading }}">Format</p>
                    <div class="grid grid-cols-[56px_1fr] gap-y-1.5 items-center font-sans-soft text-[11px] text-muted-foreground">
                        @foreach ($config['shapes'] as $shape)
                            <span>{{ $shape['label'] }}</span>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($shape['formats'] as $format)
                                    <button
                                        type="button"
                                        data-format="{{ $format['id'] }}"
                                        data-cols="{{ $format['cols'] }}"
                                        data-rows="{{ $format['rows'] }}"
                                        class="{{ $chip }}"
                                    >{{ $format['label'] }}</button>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                <div data-ref="background-field" class="hidden space-y-1.5">
                    <p class="{{ $heading }}">Fond (transparence)</p>
                    <select data-ref="background" class="{{ $field }}"></select>
                </div>
            </section>

            <section class="{{ $panel }} p-3 space-y-2">
                <div class="flex items-center justify-between">
                    <p class="{{ $heading }}">Source</p>
                    <button type="button" class="font-sans-soft text-xs text-muted-foreground hover:text-foreground underline underline-offset-2" data-action="change-image">Changer</button>
                </div>
                <label data-ref="dropzone" class="flex flex-col items-center justify-center gap-1 aspect-square border-2 border-dashed border-border rounded-lg cursor-pointer hover:bg-muted/40 transition text-center px-3">
                    <span class="font-sans-soft text-xs text-foreground">Glisser une image ici ou cliquer pour choisir</span>
                    <span class="font-sans-soft text-[11px] text-muted-foreground">PNG ou JPG, {{ intdiv($config['maxUploadKb'], 1024) }} Mo maximum</span>
                    <input type="file" accept="image/png,image/jpeg" class="sr-only" data-ref="file">
                </label>
                <div data-ref="cropper" class="hidden space-y-1.5">
                    <div class="relative inline-block max-w-full select-none touch-none">
                        <canvas data-ref="crop-canvas" class="block max-w-full rounded-lg"></canvas>
                    </div>
                    <p data-ref="crop-info" class="font-sans-soft text-[11px] text-muted-foreground"></p>
                    <button type="button" class="font-sans-soft text-[11px] text-muted-foreground hover:text-foreground underline underline-offset-2" data-action="crop-reset">Recadrage par défaut</button>
                    <button type="button" class="hidden font-sans-soft text-[11px] text-muted-foreground hover:text-foreground underline underline-offset-2" data-action="crop-unlock" title="Les versions dépendent du recadrage : le modifier crée un nouveau projet">Modifier le recadrage (nouveau projet)</button>
                </div>
            </section>

            <section class="{{ $panel }} p-3 space-y-2">
                <p class="{{ $heading }}">Redessin IA</p>
                @unless ($config['ai']['enabled'])
                    <p class="font-sans-soft text-[11px] text-muted-foreground">
                        Redessin IA désactivé (pas de clé <code class="font-mono">GEMINI_API_KEY</code>) : seul le mode test est disponible.
                    </p>
                @endunless
                <div class="space-y-1">
                    <p class="font-sans-soft text-[11px] text-muted-foreground">Nombre de dessins générés</p>
                    <div class="flex border border-border overflow-hidden font-sans-soft text-xs">
                        @foreach ($config['ai']['counts'] as $count)
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="ai-count" value="{{ $count }}" class="peer sr-only" @checked($loop->first)>
                                <span @class(['block text-center py-1 peer-checked:bg-foreground peer-checked:text-background', 'border-l border-border' => ! $loop->first])>{{ $count }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <button type="button" class="w-full px-4 py-2 bg-primary text-background font-sans-soft text-sm uppercase hover:opacity-90 transition disabled:opacity-40 disabled:cursor-not-allowed" data-action="generate" disabled>Générer</button>
                <p data-ref="status" class="hidden font-sans-soft text-[11px] text-muted-foreground text-center"></p>
                <ul data-ref="warnings" class="space-y-1 font-sans-soft text-[11px] text-amber-700"></ul>
            </section>
        </aside>

        {{-- ============================================================ Center: versions & editor --}}
        <div class="space-y-3 min-w-0">
            <section data-ref="empty-state" class="{{ $panel }} p-10 text-center">
                <p class="font-serif text-lg text-foreground">Aucune version pour l'instant</p>
                <p class="font-sans-soft text-sm text-muted-foreground mt-1">Importez une image, choisissez une palette et un format, puis générez des versions.</p>
            </section>

            <section data-ref="versions-section" class="{{ $panel }} p-3 hidden">
                <div class="flex items-center justify-between mb-2">
                    <p class="{{ $heading }}">Versions <span data-ref="versions-count" class="normal-case tracking-normal"></span></p>
                    <button type="button" class="font-sans-soft text-xs text-muted-foreground hover:text-destructive transition" data-action="clear-versions">Tout effacer</button>
                </div>
                <div data-ref="versions" class="flex gap-2 overflow-x-auto pb-1"></div>
            </section>

            <section data-ref="editor-section" class="{{ $panel }} overflow-hidden hidden">
                <div class="flex flex-wrap items-center gap-2 px-3 py-2 border-b border-border bg-muted/40">
                    <div class="flex border border-border overflow-hidden bg-card font-sans-soft text-xs">
                        <button type="button" data-tool="brush" class="px-3 py-1 data-[active=true]:bg-foreground data-[active=true]:text-background" title="Pinceau (B)">Pinceau <kbd class="opacity-60">B</kbd></button>
                        <button type="button" data-tool="fill" class="px-3 py-1 border-l border-border data-[active=true]:bg-foreground data-[active=true]:text-background" title="Remplissage (F)">Remplir <kbd class="opacity-60">F</kbd></button>
                        <button type="button" data-tool="picker" class="px-3 py-1 border-l border-border data-[active=true]:bg-foreground data-[active=true]:text-background" title="Pipette (I ou Alt + clic)">Pipette <kbd class="opacity-60">I</kbd></button>
                    </div>
                    <div class="flex border border-border overflow-hidden bg-card font-sans-soft text-xs">
                        <button type="button" class="px-2.5 py-1" data-action="undo" title="Annuler (Ctrl/Cmd + Z)">↶</button>
                        <button type="button" class="px-2.5 py-1 border-l border-border" data-action="redo" title="Rétablir (Ctrl/Cmd + Maj + Z)">↷</button>
                    </div>
                    <span class="h-5 w-px bg-border"></span>
                    <label class="flex items-center gap-1.5 font-sans-soft text-xs">
                        <input type="checkbox" data-ref="show-grid" class="rounded border-border" checked> Grille
                    </label>
                    <span data-ref="editor-info" class="ml-auto font-sans-soft text-xs text-muted-foreground"></span>
                    <button type="button" class="font-sans-soft text-xs text-muted-foreground hover:text-destructive transition" data-action="clear-edits">Effacer les retouches</button>
                </div>

                <div class="grid md:grid-cols-[minmax(0,1fr)_140px]">
                    <div class="p-4 overflow-auto bg-[repeating-conic-gradient(var(--muted)_0_25%,transparent_0_50%)] bg-[length:16px_16px]">
                        <canvas data-ref="editor" class="block mx-auto cursor-crosshair touch-none shadow-sm"></canvas>
                    </div>
                    <div class="border-t md:border-t-0 md:border-l border-border p-2 space-y-1">
                        <p class="{{ $heading }} px-1 pb-1">Essence</p>
                        <div data-ref="editor-palette" class="flex flex-wrap md:flex-col gap-1"></div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 px-3 py-1.5 border-t border-border font-sans-soft text-[11px] text-muted-foreground">
                    <span data-ref="hover-info" class="h-4"></span>
                    <span data-ref="result-info"></span>
                </div>
            </section>
        </div>

        {{-- ============================================================ Right: finishing, AI help, result --}}
        <aside class="space-y-3 lg:col-span-2 xl:col-span-1 xl:sticky xl:top-20">
            <section data-ref="finish-section" class="{{ $panel }} p-3 space-y-2 hidden">
                <p class="{{ $heading }}">Motif de fond</p>
                <select data-ref="motif" class="{{ $field }}">
                    @foreach ($config['motifs'] as $key => $motifLabel)
                        <option value="{{ $key }}">{{ $motifLabel }}</option>
                    @endforeach
                </select>
                <div class="grid grid-cols-2 gap-1.5">
                    <select data-ref="density" class="{{ $field }}">
                        @foreach ($config['densities'] as $key => $densityLabel)
                            <option value="{{ $key }}">{{ $densityLabel }}</option>
                        @endforeach
                    </select>
                    <select data-ref="motif-essence" class="{{ $field }}"></select>
                </div>
            </section>

            <section data-ref="ai-help" class="{{ $panel }} p-3 space-y-2 hidden">
                <div class="flex items-center justify-between">
                    <p class="{{ $heading }}">Aide IA</p>
                    <div class="flex border border-border overflow-hidden font-sans-soft text-[11px]">
                        <button type="button" data-ai-tab="suggestions" class="{{ $segment }}">Suggestions</button>
                        <button type="button" data-ai-tab="refine" class="{{ $segment }} border-l border-border">Raffiner</button>
                    </div>
                </div>

                <div data-ai-panel="suggestions" class="space-y-2 hidden">
                    <ul data-ref="suggestions" class="space-y-0.5 max-h-64 overflow-auto"></ul>
                    <div class="flex gap-2">
                        <button type="button" class="{{ $smallButton }} flex-1" data-action="suggest" disabled>Demander</button>
                        <button type="button" class="{{ $smallButton }} flex-1 !bg-foreground !text-background !border-foreground hidden" data-action="apply-suggestions">Appliquer</button>
                    </div>
                </div>

                <div data-ai-panel="refine" class="space-y-2">
                    <textarea data-ref="remarks" rows="4" class="{{ $field }} resize-none" placeholder="Une remarque par ligne"></textarea>
                    <button type="button" class="{{ $smallButton }} w-full" data-action="refine" disabled>Raffiner la version</button>
                </div>
            </section>

            <section data-ref="result-section" class="{{ $panel }} overflow-hidden hidden">
                <div class="flex border-b border-border font-sans-soft text-xs">
                    <button type="button" data-result-tab="wood" class="{{ $tab }}">Bois</button>
                    <button type="button" data-result-tab="flat" class="{{ $tab }}">Aplats</button>
                    <button type="button" data-result-tab="plan" class="{{ $tab }}">Plan</button>
                </div>
                <div class="p-3 space-y-2">
                    <div data-result-panel="wood"><div data-ref="result-wood-placeholder" class="aspect-square rounded-md bg-muted animate-pulse flex items-center justify-center font-sans-soft text-xs text-muted-foreground">Rendu bois en cours…</div><img data-ref="result-wood" alt="Rendu bois" class="hidden w-full h-auto rounded-md bg-muted"></div>
                    <div data-result-panel="flat" class="hidden"><canvas data-ref="result-flat" class="w-full h-auto rounded-md [image-rendering:pixelated]"></canvas></div>
                    <div data-result-panel="plan" class="hidden"><div data-ref="result-plan-placeholder" class="aspect-square rounded-md bg-muted animate-pulse flex items-center justify-center font-sans-soft text-xs text-muted-foreground">Plan de montage en cours…</div><img data-ref="result-plan" alt="Plan de montage" class="hidden w-full h-auto rounded-md bg-white"></div>

                    <div class="grid grid-cols-3 gap-1.5">
                        <button type="button" data-result-tab="wood" class="rounded-md border border-border p-1 data-[active=true]:border-foreground"><span data-ref="thumb-wood-placeholder" class="block w-full aspect-square rounded-sm bg-muted animate-pulse"></span><img data-ref="thumb-wood" alt="" class="hidden w-full aspect-square object-cover rounded-sm bg-muted"></button>
                        <button type="button" data-result-tab="flat" class="rounded-md border border-border p-1 data-[active=true]:border-foreground"><canvas data-ref="thumb-flat" class="w-full aspect-square rounded-sm [image-rendering:pixelated]"></canvas></button>
                        <button type="button" data-result-tab="plan" class="rounded-md border border-border p-1 data-[active=true]:border-foreground"><span data-ref="thumb-plan-placeholder" class="block w-full aspect-square rounded-sm bg-muted animate-pulse"></span><img data-ref="thumb-plan" alt="" class="hidden w-full aspect-square object-cover object-top rounded-sm bg-white"></button>
                    </div>
                    <p data-ref="missing-textures" class="font-sans-soft text-[11px] text-muted-foreground"></p>

                    <button type="button" class="w-full px-4 py-2 bg-primary text-background font-sans-soft text-sm uppercase hover:opacity-90 transition" data-action="download-zip">Tout télécharger (.zip)</button>
                    <div class="flex justify-center gap-3 font-sans-soft text-[11px] text-muted-foreground">
                        <button type="button" class="underline underline-offset-2 hover:text-foreground" data-download="wood">Bois .png</button>
                        <button type="button" class="underline underline-offset-2 hover:text-foreground" data-download="image">Aplats .png</button>
                        <button type="button" class="underline underline-offset-2 hover:text-foreground" data-download="plan">Plan .png</button>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    {{-- ============================================================ Status bar --}}
    <div class="sticky bottom-3 mt-4 z-30">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 px-4 py-2 rounded-full bg-foreground text-background font-sans-soft text-xs shadow-lg">
            <span data-ref="ai-progress" class="hidden" aria-live="polite">
                <span class="inline-flex items-center gap-2 pr-3 mr-1 border-r border-background/20">
                    <span class="w-3.5 h-3.5 rounded-full border-2 border-background/30 border-t-primary animate-spin"></span>
                    <span data-ref="ai-progress-message"></span>
                    <span data-ref="ai-progress-timer" class="font-mono tabular-nums text-primary">00:00</span>
                    <button type="button" class="px-2 py-0.5 border border-background/30 hover:bg-background/10 transition" data-action="stop">Arrêter</button>
                </span>
            </span>
            <span data-ref="stats-mode" @class(['hidden' => ! $config['ai']['testModeByDefault']])><span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Mode test</span></span>
            <span><span class="opacity-60">Modèle</span> <span data-ref="stats-models" class="font-mono">{{ $config['ai']['imageModel'] }}</span></span>
            <span><span class="opacity-60">Coût</span> <span data-ref="stats-cost" class="font-mono">0.0000 $</span></span>
            <span><span class="opacity-60">Dernière exécution</span> <span data-ref="stats-last" class="font-mono tabular-nums">–</span></span>
            <span><span class="opacity-60">Total</span> <span data-ref="stats-total" class="font-mono tabular-nums">00:00</span></span>
            <span class="ml-auto opacity-60">Raccourcis : B · F · I · 1–9 · ⌘Z</span>
        </div>
    </div>
</div>

@vite('resources/js/fragment-tool/index.js')
@endif
@endsection
