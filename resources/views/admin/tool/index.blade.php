@extends('layouts.admin')

@section('title', 'Outil Fragment')

@section('main_class', 'max-w-7xl')

@section('content')
@php
    $card = 'border border-border rounded-2xl bg-card p-5';
    $label = 'font-sans-soft text-xs uppercase tracking-widest text-muted-foreground mb-2';
    $button = 'px-4 py-2 rounded-full font-sans-soft text-sm border border-border bg-card hover:bg-muted/60 transition disabled:opacity-40 disabled:cursor-not-allowed';
    $primary = 'px-5 py-2.5 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition disabled:opacity-40 disabled:cursor-not-allowed';
    $input = 'w-full px-3 py-2 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition';
@endphp

<div class="mb-8">
    <h1 class="font-serif text-3xl text-foreground">Outil Fragment</h1>
    <p class="font-sans-soft text-sm text-muted-foreground mt-1">Transformer une image en pixel art bois et en plan de montage.</p>
</div>

@if ($config['palettes']->isEmpty() || $config['shapes']->isEmpty())
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
<div id="fragment-tool" class="space-y-6">
    <script type="application/json" data-ref="config">@json($config)</script>

    <div data-ref="error" class="hidden p-4 bg-red-50 border border-red-200 rounded-xl font-sans-soft text-sm text-red-700"></div>

    {{-- 1. Image, crop, format, palette --}}
    <section class="{{ $card }}">
        <div class="grid lg:grid-cols-[1fr_340px] gap-6">
            <div>
                <p class="{{ $label }}">1 · Image et recadrage</p>
                <label data-ref="dropzone" class="flex flex-col items-center justify-center gap-2 h-72 border-2 border-dashed border-border rounded-2xl cursor-pointer hover:bg-muted/40 transition text-center px-4">
                    <span class="font-sans-soft text-sm text-foreground">Glisser une image ici ou cliquer pour choisir</span>
                    <span class="font-sans-soft text-xs text-muted-foreground">PNG ou JPG, {{ intdiv($config['maxUploadKb'], 1024) }} Mo maximum</span>
                    <input type="file" accept="image/png,image/jpeg" class="sr-only" data-ref="file">
                </label>
                <div data-ref="cropper" class="hidden">
                    <div class="relative inline-block max-w-full select-none touch-none">
                        <canvas data-ref="crop-canvas" class="block max-w-full rounded-xl"></canvas>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        <button type="button" class="{{ $button }}" data-action="crop-reset">Recadrage par défaut</button>
                        <button type="button" class="{{ $button }}" data-action="change-image">Changer d'image</button>
                        <span data-ref="crop-info" class="font-sans-soft text-xs text-muted-foreground"></span>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <p class="{{ $label }}">Palette</p>
                    <select data-ref="palette" class="{{ $input }}">
                        @foreach ($config['palettes'] as $palette)
                            <option value="{{ $palette['id'] }}">{{ $palette['name'] }} ({{ count($palette['essences']) }} essences)</option>
                        @endforeach
                    </select>
                    <div data-ref="palette-swatches" class="flex flex-wrap gap-1 mt-2"></div>
                </div>

                <div>
                    <p class="{{ $label }}">Format</p>
                    <div class="space-y-3">
                        @foreach ($config['shapes'] as $shape)
                            <div>
                                <p class="font-sans-soft text-xs text-muted-foreground mb-1.5">{{ $shape['label'] }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($shape['formats'] as $format)
                                        <button
                                            type="button"
                                            data-format="{{ $format['id'] }}"
                                            data-cols="{{ $format['cols'] }}"
                                            data-rows="{{ $format['rows'] }}"
                                            class="px-3 py-1.5 rounded-full border border-border font-sans-soft text-sm font-mono hover:bg-muted/60 transition data-[active=true]:bg-foreground data-[active=true]:text-background data-[active=true]:border-foreground"
                                        >{{ $format['label'] }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div data-ref="background-field" class="hidden">
                    <p class="{{ $label }}">Essence de fond (transparence)</p>
                    <select data-ref="background" class="{{ $input }}"></select>
                </div>
            </div>
        </div>
    </section>

    {{-- 2. Generate --}}
    <section class="{{ $card }}" data-ref="generate-section">
        <p class="{{ $label }} mb-3">2 · Générer des versions</p>

        <div class="max-w-xl">
            <div class="space-y-3">
                <p class="font-sans-soft text-sm text-foreground font-medium">Redessin IA</p>
                @unless ($config['ai']['enabled'])
                    <p class="font-sans-soft text-sm text-muted-foreground">
                        Redessin IA désactivé : ajoutez <code class="font-mono">GEMINI_API_KEY</code> dans le fichier <code class="font-mono">.env</code>, ou activez le mode test.
                    </p>
                @endunless
                <div class="flex items-center gap-2">
                    <span class="font-sans-soft text-sm text-muted-foreground">Images :</span>
                    @foreach ($config['ai']['counts'] as $count)
                        <label class="cursor-pointer">
                            <input type="radio" name="ai-count" value="{{ $count }}" class="peer sr-only" @checked($loop->first)>
                            <span class="inline-block px-3 py-1 rounded-full border border-border font-sans-soft text-sm peer-checked:bg-foreground peer-checked:text-background">{{ $count }}</span>
                        </label>
                    @endforeach
                </div>
                <textarea data-ref="hint" rows="2" maxlength="{{ $config['ai']['hintMaxLength'] }}" class="{{ $input }}" placeholder="Consigne facultative (ex. : garder le chapeau rouge)"></textarea>
                <div class="flex flex-wrap items-center gap-4">
                    <button type="button" class="{{ $primary }}" data-action="generate" data-ai disabled>Générer</button>
                    <label class="flex items-center gap-1.5 font-sans-soft text-sm cursor-pointer">
                        <input type="checkbox" data-ref="test-mode" class="rounded border-border">
                        Mode test <span class="text-muted-foreground">(sans appel à Gemini, gratuit)</span>
                    </label>
                </div>
            </div>
        </div>

        <div data-ref="status" class="hidden mt-4 font-sans-soft text-sm text-muted-foreground"></div>
        <ul data-ref="warnings" class="mt-2 space-y-1 font-sans-soft text-xs text-amber-700"></ul>
    </section>

    {{-- 3. Versions --}}
    <section class="{{ $card }} hidden" data-ref="versions-section">
        <div class="flex items-center justify-between mb-3">
            <p class="{{ $label }} mb-0">3 · Choisir une version</p>
            <button type="button" class="font-sans-soft text-sm text-muted-foreground hover:text-destructive transition" data-action="clear-versions">Tout effacer</button>
        </div>
        <div data-ref="versions" class="flex flex-wrap gap-3"></div>

        <div data-ref="refine" class="hidden mt-5 pt-5 border-t border-border space-y-3">
            <p class="font-sans-soft text-sm text-foreground font-medium">Raffiner la version choisie</p>
            <textarea data-ref="remarks" rows="3" class="{{ $input }}" placeholder="Une remarque par ligne"></textarea>
            <button type="button" class="{{ $button }}" data-action="refine" data-ai disabled>Raffiner</button>
        </div>
    </section>

    {{-- 4. Finishing --}}
    <section class="{{ $card }} hidden" data-ref="finish-section">
        <p class="{{ $label }}">4 · Finitions</p>

        <div class="grid xl:grid-cols-[1fr_320px] gap-6">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <div class="flex rounded-full border border-border overflow-hidden">
                        <button type="button" data-tool="brush" class="px-3 py-1.5 font-sans-soft text-sm data-[active=true]:bg-foreground data-[active=true]:text-background" title="Pinceau (B)">Pinceau</button>
                        <button type="button" data-tool="fill" class="px-3 py-1.5 font-sans-soft text-sm border-l border-border data-[active=true]:bg-foreground data-[active=true]:text-background" title="Remplissage (F)">Remplir</button>
                        <button type="button" data-tool="picker" class="px-3 py-1.5 font-sans-soft text-sm border-l border-border data-[active=true]:bg-foreground data-[active=true]:text-background" title="Pipette (I ou Alt + clic)">Pipette</button>
                    </div>
                    <button type="button" class="{{ $button }}" data-action="undo" title="Ctrl/Cmd + Z">Annuler</button>
                    <button type="button" class="{{ $button }}" data-action="redo" title="Ctrl/Cmd + Maj + Z">Rétablir</button>
                    <button type="button" class="{{ $button }}" data-action="clear-edits">Effacer les retouches</button>
                    <label class="flex items-center gap-1.5 font-sans-soft text-sm">
                        <input type="checkbox" data-ref="show-grid" class="rounded border-border" checked> Grille
                    </label>
                </div>
                <div data-ref="editor-palette" class="flex flex-wrap gap-1.5 mb-3"></div>
                <div class="overflow-auto">
                    <canvas data-ref="editor" class="block cursor-crosshair touch-none"></canvas>
                </div>
                <p data-ref="hover-info" class="font-sans-soft text-xs text-muted-foreground mt-2 h-4"></p>
            </div>

            <div class="space-y-5">
                <div class="space-y-2">
                    <p class="font-sans-soft text-sm text-foreground font-medium">Motif de fond</p>
                    <select data-ref="motif" class="{{ $input }}">
                        @foreach ($config['motifs'] as $key => $motifLabel)
                            <option value="{{ $key }}">{{ $motifLabel }}</option>
                        @endforeach
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <select data-ref="density" class="{{ $input }}">
                            @foreach ($config['densities'] as $key => $densityLabel)
                                <option value="{{ $key }}">{{ $densityLabel }}</option>
                            @endforeach
                        </select>
                        <select data-ref="motif-essence" class="{{ $input }}"></select>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="font-sans-soft text-sm text-foreground font-medium">Suggestions de Gemini</p>
                    <button type="button" class="{{ $button }}" data-action="suggest" data-ai disabled>Demander des suggestions</button>
                    <ul data-ref="suggestions" class="space-y-1 max-h-72 overflow-auto"></ul>
                    <button type="button" class="{{ $button }} hidden" data-action="apply-suggestions">Appliquer la sélection</button>
                </div>
            </div>
        </div>
    </section>

    {{-- AI session stats, fixed while scrolling --}}
    <aside class="fixed bottom-4 right-4 z-40 w-64 {{ $card }} p-4 shadow-lg space-y-1.5 font-sans-soft text-xs">
        <div class="flex items-center justify-between">
            <p class="uppercase tracking-widest text-muted-foreground">Session IA</p>
            <span data-ref="stats-mode" class="hidden px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Mode test</span>
        </div>
        <p class="text-muted-foreground">Modèle : <span data-ref="stats-models" class="text-foreground font-mono break-all">{{ $config['ai']['imageModel'] }}</span></p>
        <p class="text-muted-foreground">Coût : <span data-ref="stats-cost" class="text-foreground font-mono">0.0000 $</span></p>
        <p class="text-muted-foreground">Dernière exécution : <span data-ref="stats-last" class="text-foreground font-mono tabular-nums">–</span></p>
        <p class="text-muted-foreground">Temps total : <span data-ref="stats-total" class="text-foreground font-mono tabular-nums">00:00</span></p>
    </aside>

    {{-- AI progress overlay --}}
    <div data-ref="ai-overlay" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm px-4" role="dialog" aria-modal="true" aria-live="polite">
        <div class="w-full max-w-sm {{ $card }} shadow-xl text-center space-y-4">
            <div class="mx-auto h-10 w-10 rounded-full border-4 border-border border-t-foreground animate-spin"></div>
            <p data-ref="ai-overlay-message" class="font-sans-soft text-sm text-foreground"></p>
            <p data-ref="ai-overlay-timer" class="font-mono text-3xl text-foreground tabular-nums">00:00</p>
            <button type="button" class="{{ $button }}" data-action="stop">Arrêter</button>
        </div>
    </div>

    {{-- 5. Result --}}
    <section class="{{ $card }} hidden" data-ref="result-section">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
            <p class="{{ $label }} mb-0">5 · Résultat</p>
            <div class="flex flex-wrap items-center gap-3">
                <p data-ref="result-info" class="font-sans-soft text-sm text-muted-foreground"></p>
                <button type="button" class="{{ $primary }}" data-action="download-zip">Tout télécharger (.zip)</button>
            </div>
        </div>
        <div class="grid md:grid-cols-3 gap-4">
            <figure class="space-y-2">
                <canvas data-ref="result-flat" class="w-full h-auto rounded-lg border border-border [image-rendering:pixelated]"></canvas>
                <button type="button" class="{{ $button }} w-full" data-download="image">Télécharger l'image en aplats</button>
            </figure>
            <figure class="space-y-2">
                <img data-ref="result-wood" alt="Rendu bois" class="w-full h-auto rounded-lg border border-border bg-muted">
                <button type="button" class="{{ $button }} w-full" data-download="wood">Télécharger le rendu bois</button>
                <p data-ref="missing-textures" class="font-sans-soft text-xs text-muted-foreground"></p>
            </figure>
            <figure class="space-y-2">
                <img data-ref="result-plan" alt="Plan de montage" class="w-full h-auto rounded-lg border border-border bg-white">
                <button type="button" class="{{ $button }} w-full" data-download="plan">Télécharger le plan de montage</button>
            </figure>
        </div>
    </section>
</div>

@vite('resources/js/fragment-tool/index.js')
@endif
@endsection
