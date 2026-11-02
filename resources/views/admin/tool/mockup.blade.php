@extends('layouts.admin')

@section('title', 'Maquette outil')

@section('main_class', 'max-w-[1600px]')

@section('content')
@php
    $panel = 'border border-border rounded-xl bg-card';
    $heading = 'font-sans-soft text-[11px] uppercase tracking-widest text-muted-foreground';
    $chip = 'px-2.5 py-1 rounded-full border border-border font-sans-soft text-xs';
    $chipActive = 'px-2.5 py-1 rounded-full border border-foreground bg-foreground text-background font-sans-soft text-xs';
    $smallButton = 'px-3 py-1.5 rounded-full border border-border bg-card font-sans-soft text-xs hover:bg-muted/60 transition';
    $field = 'w-full px-2.5 py-1.5 bg-background border border-border rounded-lg font-sans-soft text-xs text-foreground';

    $essences = [
        '1' => ['Sycomore', '#f2cc96'],
        '2' => ['Hêtre', '#d6b47c'],
        '3' => ['Poirier', '#c26d2d'],
        '4' => ['Merisier', '#a0522d'],
        '5' => ['Noyer', '#5b3a24'],
        '6' => ['Tulipier noir', '#2f2a26'],
        '7' => ['Érable', '#efe3c8'],
    ];

    $pattern = [
        'BBBBBBBBBBBBBBBB',
        'BBDDBBBBBBBBDDBB',
        'BBDODBBBBBBDODBB',
        'BBDOODDDDDDOODBB',
        'BBDOOOOOOOOOODBB',
        'BDOOOOOOOOOOOODB',
        'BDOOWWOOOOWWOODB',
        'BDOOWDOOOOWDOODB',
        'BDOOOOOOOOOOOODB',
        'BDOOOOODDOOOOODB',
        'BDOWWOOOOOOWWODB',
        'BBDOWWWDDWWWODBB',
        'BBBDOOWWWWOODBBB',
        'BBBBDDOOOODDBBBB',
        'BBBBBBDDDDBBBBBB',
        'BBBBBBBBBBBBBBBB',
    ];

    $grid = fn (array $map) => array_map(
        fn (string $line) => array_map(fn (string $letter) => [$map[$letter], $essences[$map[$letter]][1]], str_split($line)),
        $pattern,
    );

    $versions = [
        ['n' => 1, 'label' => 'IA · Standard', 'score' => '8/10', 'grid' => $grid(['B' => '2', 'D' => '6', 'O' => '3', 'W' => '7']), 'selected' => true],
        ['n' => 2, 'label' => 'IA · Contrasté', 'score' => '9/10', 'grid' => $grid(['B' => '1', 'D' => '6', 'O' => '4', 'W' => '7']), 'preferred' => true],
        ['n' => 3, 'label' => 'IA · Fidèle', 'score' => '7/10', 'grid' => $grid(['B' => '2', 'D' => '5', 'O' => '3', 'W' => '1'])],
        ['n' => 4, 'label' => 'IA · Mignon', 'score' => '6/10', 'grid' => $grid(['B' => '1', 'D' => '5', 'O' => '4', 'W' => '7'])],
        ['n' => 5, 'label' => 'Raffinée (de n° 1)', 'score' => '–', 'grid' => $grid(['B' => '2', 'D' => '6', 'O' => '4', 'W' => '7'])],
    ];
    $current = $versions[0]['grid'];
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-baseline gap-3">
        <h1 class="font-serif text-2xl text-foreground">Outil Fragment</h1>
        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-sans-soft text-[11px]">Maquette statique</span>
    </div>

    {{-- Progress through the flow --}}
    <ol class="flex flex-wrap items-center gap-1 font-sans-soft text-xs">
        @foreach (['Image', 'Générer', 'Choisir', 'Finitions', 'Exporter'] as $index => $step)
            <li class="flex items-center gap-1">
                <span @class([
                    'flex items-center gap-1.5 px-2.5 py-1 rounded-full',
                    'bg-foreground text-background' => $index === 3,
                    'text-foreground' => $index < 3,
                    'text-muted-foreground' => $index > 3,
                ])>
                    <span @class(['w-4 h-4 rounded-full flex items-center justify-center text-[10px]', 'bg-primary text-primary-foreground' => $index < 3, 'bg-background text-foreground' => $index === 3, 'border border-border' => $index > 3])>{{ $index < 3 ? '✓' : $index + 1 }}</span>
                    {{ $step }}
                </span>
                @unless ($loop->last)
                    <span class="text-border">—</span>
                @endunless
            </li>
        @endforeach
    </ol>

    <div class="flex items-center gap-2">
        <label class="flex items-center gap-1.5 font-sans-soft text-xs cursor-pointer">
            <span class="relative inline-block w-8 h-4 rounded-full bg-primary"><span class="absolute right-0.5 top-0.5 w-3 h-3 rounded-full bg-card"></span></span>
            Mode test
        </label>
        <button type="button" class="{{ $smallButton }}">Nouveau projet</button>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-[250px_minmax(0,1fr)] xl:grid-cols-[250px_minmax(0,1fr)_300px] items-start">

    {{-- ============================================================ Left: source & generation --}}
    <aside class="space-y-3 lg:sticky lg:top-20">
        <section class="{{ $panel }} p-3 space-y-2">
            <div class="flex items-center justify-between">
                <p class="{{ $heading }}">Source</p>
                <button type="button" class="font-sans-soft text-xs text-muted-foreground hover:text-foreground underline underline-offset-2">Changer</button>
            </div>
            <div class="relative aspect-square rounded-lg overflow-hidden" style="background: radial-gradient(circle at 50% 45%, #e8a066 0 28%, #b86a3c 29% 34%, #9fb4c8 35%);">
                <div class="absolute inset-[8%] border-2 border-white rounded-sm shadow-[0_0_0_999px_rgba(0,0,0,0.35)]"></div>
                <span class="absolute bottom-1.5 left-1.5 px-1.5 py-0.5 rounded bg-black/60 text-white font-mono text-[10px]">640 × 640</span>
            </div>
            <p class="font-sans-soft text-xs text-foreground truncate">chat_roux.jpg</p>
            <p class="font-sans-soft text-[11px] text-muted-foreground">40 px par case · <button type="button" class="underline underline-offset-2">recadrage par défaut</button></p>
        </section>

        <section class="{{ $panel }} p-3 space-y-3">
            <div class="space-y-1.5">
                <p class="{{ $heading }}">Palette</p>
                <div class="{{ $field }} flex items-center justify-between">Atelier classique (7) <span class="text-muted-foreground">▾</span></div>
                <div class="flex flex-wrap gap-1">
                    @foreach ($essences as $code => [$name, $hex])
                        <span class="w-4 h-4 rounded border border-border" style="background-color: {{ $hex }}" title="{{ $code }} · {{ $name }}"></span>
                    @endforeach
                </div>
            </div>

            <div class="space-y-1.5">
                <p class="{{ $heading }}">Format</p>
                <div class="grid grid-cols-[52px_1fr] gap-y-1.5 items-center font-sans-soft text-[11px] text-muted-foreground">
                    <span>Carré</span>
                    <div class="flex flex-wrap gap-1"><span class="{{ $chipActive }} font-mono">16×16</span><span class="{{ $chip }} font-mono">24×24</span><span class="{{ $chip }} font-mono">32×32</span></div>
                    <span>Paysage</span>
                    <div class="flex flex-wrap gap-1"><span class="{{ $chip }} font-mono">24×16</span><span class="{{ $chip }} font-mono">32×24</span></div>
                    <span>Portrait</span>
                    <div class="flex flex-wrap gap-1"><span class="{{ $chip }} font-mono">16×24</span></div>
                </div>
            </div>
        </section>

        <section class="{{ $panel }} p-3 space-y-2">
            <div class="flex items-center justify-between">
                <p class="{{ $heading }}">Redessin IA</p>
                <div class="flex rounded-full border border-border overflow-hidden font-sans-soft text-xs">
                    <span class="px-2.5 py-0.5">1</span>
                    <span class="px-2.5 py-0.5 border-l border-border">2</span>
                    <span class="px-2.5 py-0.5 border-l border-border bg-foreground text-background">4</span>
                </div>
            </div>
            <div class="{{ $field }} h-14 text-muted-foreground">Consigne facultative (ex. : garder le chapeau rouge)</div>
            <button type="button" class="w-full px-4 py-2 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition">Générer 4 versions</button>
            <p class="font-sans-soft text-[11px] text-muted-foreground text-center">≈ 0,32 $ · gratuit en mode test</p>
        </section>
    </aside>

    {{-- ============================================================ Center: versions & editor --}}
    <div class="space-y-3 min-w-0">
        {{-- Versions strip --}}
        <section class="{{ $panel }} p-3">
            <div class="flex items-center justify-between mb-2">
                <p class="{{ $heading }}">Versions <span class="normal-case tracking-normal">· {{ count($versions) }}</span></p>
                <button type="button" class="font-sans-soft text-xs text-muted-foreground hover:text-destructive transition">Tout effacer</button>
            </div>
            <div class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($versions as $version)
                    <div @class([
                        'relative shrink-0 w-28 p-1.5 rounded-lg border',
                        'border-foreground ring-1 ring-foreground' => $version['selected'] ?? false,
                        'border-border hover:bg-muted/50' => ! ($version['selected'] ?? false),
                    ])>
                        @include('admin.tool.partials.mock-grid', ['cells' => $version['grid'], 'class' => 'rounded overflow-hidden'])
                        <div class="flex items-center justify-between mt-1 font-sans-soft text-[11px]">
                            <span class="text-foreground">n° {{ $version['n'] }}</span>
                            <span class="font-mono text-muted-foreground">{{ $version['score'] }}</span>
                        </div>
                        <p class="font-sans-soft text-[10px] text-muted-foreground truncate">{{ $version['label'] }}</p>
                        @if ($version['preferred'] ?? false)
                            <span class="absolute top-0.5 right-1.5 text-amber-500 text-sm" title="Préférée du juge">★</span>
                        @endif
                    </div>
                @endforeach
                <div class="shrink-0 w-28 rounded-lg border border-dashed border-border flex flex-col items-center justify-center gap-1 font-sans-soft text-[11px] text-muted-foreground">
                    <span class="text-lg leading-none">+</span>
                    Générer encore
                </div>
            </div>
        </section>

        <section class="{{ $panel }} overflow-hidden">
            {{-- Toolbar --}}
            <div class="flex flex-wrap items-center gap-2 px-3 py-2 border-b border-border bg-muted/40">
                <div class="flex rounded-full border border-border overflow-hidden bg-card font-sans-soft text-xs">
                    <span class="px-3 py-1 bg-foreground text-background">Pinceau <kbd class="opacity-60">B</kbd></span>
                    <span class="px-3 py-1 border-l border-border">Remplir <kbd class="opacity-60">F</kbd></span>
                    <span class="px-3 py-1 border-l border-border">Pipette <kbd class="opacity-60">I</kbd></span>
                </div>
                <div class="flex rounded-full border border-border overflow-hidden bg-card font-sans-soft text-xs">
                    <span class="px-2.5 py-1" title="Annuler">↶</span>
                    <span class="px-2.5 py-1 border-l border-border" title="Rétablir">↷</span>
                </div>
                <span class="h-5 w-px bg-border"></span>
                <label class="flex items-center gap-1.5 font-sans-soft text-xs"><input type="checkbox" checked class="rounded border-border"> Grille</label>
                <div class="flex items-center rounded-full border border-border bg-card font-sans-soft text-xs">
                    <span class="px-2 py-1">−</span><span class="px-1 py-1 font-mono">100 %</span><span class="px-2 py-1">+</span>
                </div>
                <span class="ml-auto font-sans-soft text-xs text-muted-foreground">Version n° 1 · 3 retouches</span>
                <button type="button" class="font-sans-soft text-xs text-muted-foreground hover:text-destructive transition">Effacer les retouches</button>
            </div>

            <div class="grid md:grid-cols-[minmax(0,1fr)_120px]">
                {{-- Canvas --}}
                <div class="p-4 flex items-center justify-center bg-[repeating-conic-gradient(var(--muted)_0_25%,transparent_0_50%)] bg-[length:16px_16px]">
                    <div class="w-full max-w-[520px] shadow-sm">
                        @include('admin.tool.partials.mock-grid', ['cells' => $current, 'class' => 'gap-px bg-black/10 p-px'])
                    </div>
                </div>

                {{-- Brush essences --}}
                <div class="border-t md:border-t-0 md:border-l border-border p-2 space-y-1">
                    <p class="{{ $heading }} px-1 pb-1">Essence</p>
                    @foreach ($essences as $code => [$name, $hex])
                        <div @class(['flex items-center gap-2 px-1.5 py-1 rounded-lg font-sans-soft text-xs', 'bg-foreground/10 ring-1 ring-foreground' => $code === '3'])>
                            <span class="w-4 h-4 rounded-full border border-border shrink-0" style="background-color: {{ $hex }}"></span>
                            <span class="truncate">{{ $name }}</span>
                            <span class="ml-auto font-mono text-[10px] text-muted-foreground">{{ $code }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-between px-3 py-1.5 border-t border-border font-sans-soft text-[11px] text-muted-foreground">
                <span>Ligne 7, colonne 5 · 7 Érable</span>
                <span>5 essences · 256 morceaux</span>
            </div>
        </section>
    </div>

    {{-- ============================================================ Right: finishing, AI help, result --}}
    <aside class="space-y-3 lg:col-span-2 xl:col-span-1 xl:sticky xl:top-20">
        <section class="{{ $panel }} p-3 space-y-2">
            <p class="{{ $heading }}">Motif de fond</p>
            <div class="grid grid-cols-3 gap-1.5">
                <div class="{{ $field }} col-span-3 flex justify-between">Pois <span class="text-muted-foreground">▾</span></div>
                <div class="{{ $field }} col-span-2 flex justify-between">Moyenne <span class="text-muted-foreground">▾</span></div>
                <div class="{{ $field }} flex items-center gap-1"><span class="w-3 h-3 rounded-full" style="background-color: #c26d2d"></span>3</div>
            </div>
        </section>

        <section class="{{ $panel }} p-3 space-y-2">
            <div class="flex items-center justify-between">
                <p class="{{ $heading }}">Aide IA</p>
                <div class="flex rounded-full border border-border overflow-hidden font-sans-soft text-[11px]">
                    <span class="px-2.5 py-0.5 bg-foreground text-background">Suggestions</span>
                    <span class="px-2.5 py-0.5 border-l border-border">Raffiner</span>
                </div>
            </div>
            <ul class="space-y-0.5">
                @foreach ([['L6 C7', '6', 'Ajouter un œil', true], ['L12 C8', '3', 'Contour du museau', true], ['L3 C2', '2', 'Morceau isolé dans le fond', false]] as [$cell, $code, $reason, $checked])
                    <li class="flex items-start gap-2 px-1.5 py-1 rounded-lg hover:bg-muted/50 font-sans-soft text-xs">
                        <input type="checkbox" @checked($checked) class="mt-0.5 rounded border-border">
                        <span class="w-3.5 h-3.5 mt-0.5 rounded border border-border shrink-0" style="background-color: {{ $essences[$code][1] }}"></span>
                        <span><span class="font-mono text-muted-foreground">{{ $cell }}</span> → {{ $essences[$code][0] }} · {{ $reason }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="flex gap-2">
                <button type="button" class="{{ $smallButton }} flex-1">Redemander</button>
                <button type="button" class="{{ $smallButton }} flex-1 !bg-foreground !text-background !border-foreground">Appliquer (2)</button>
            </div>
        </section>

        <section class="{{ $panel }} overflow-hidden">
            <div class="flex border-b border-border font-sans-soft text-xs">
                <span class="flex-1 text-center py-2 border-b-2 border-foreground text-foreground">Bois</span>
                <span class="flex-1 text-center py-2 text-muted-foreground">Aplats</span>
                <span class="flex-1 text-center py-2 text-muted-foreground">Plan</span>
            </div>
            <div class="p-3 space-y-2">
                @include('admin.tool.partials.mock-grid', ['cells' => $current, 'mode' => 'wood', 'class' => 'rounded-md overflow-hidden'])
                <div class="grid grid-cols-3 gap-1.5">
                    @foreach (['wood' => 'Bois', 'flat' => 'Aplats', 'plan' => 'Plan'] as $mode => $label)
                        <div @class(['rounded-md border p-1', 'border-foreground' => $mode === 'wood', 'border-border' => $mode !== 'wood'])>
                            @include('admin.tool.partials.mock-grid', ['cells' => $current, 'mode' => $mode, 'class' => 'rounded-sm overflow-hidden'])
                        </div>
                    @endforeach
                </div>
                <button type="button" class="w-full px-4 py-2 bg-foreground text-background rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition">Tout télécharger (.zip)</button>
                <div class="flex justify-center gap-3 font-sans-soft text-[11px] text-muted-foreground">
                    <span class="underline underline-offset-2">Bois .png</span>
                    <span class="underline underline-offset-2">Aplats .png</span>
                    <span class="underline underline-offset-2">Plan .png</span>
                </div>
            </div>
        </section>
    </aside>
</div>

{{-- ============================================================ Status bar --}}
<div class="sticky bottom-3 mt-4 z-30">
    <div class="flex flex-wrap items-center gap-x-5 gap-y-1 px-4 py-2 rounded-full bg-foreground text-background font-sans-soft text-xs shadow-lg">
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Mode test</span>
        <span><span class="opacity-60">Modèle</span> <span class="font-mono">gemini-nano-banana-2.1</span></span>
        <span><span class="opacity-60">Coût</span> <span class="font-mono">0.0000 $</span></span>
        <span><span class="opacity-60">Dernière exécution</span> <span class="font-mono tabular-nums">00:06</span></span>
        <span><span class="opacity-60">Total</span> <span class="font-mono tabular-nums">00:14</span></span>
        <span class="ml-auto opacity-60">Raccourcis : B · F · I · 1–9 · ⌘Z</span>
    </div>
</div>
@endsection
