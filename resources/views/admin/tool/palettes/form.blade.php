@extends('layouts.admin')

@section('title', $palette->exists ? 'Modifier la palette' : 'Nouvelle palette')

@section('content')
<div class="mb-8">
    <a href="{{ route('admin.palettes.index') }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
        ← Palettes
    </a>
    <h1 class="font-serif text-3xl text-foreground mt-3">
        {{ $palette->exists ? 'Modifier la palette' : 'Nouvelle palette' }}
    </h1>
</div>

@if ($errors->any())
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
        @foreach ($errors->all() as $error)
            <p class="font-sans-soft text-sm text-red-600">{{ $error }}</p>
        @endforeach
    </div>
@endif

<form
    method="POST"
    action="{{ $palette->exists ? route('admin.palettes.update', $palette) : route('admin.palettes.store') }}"
    class="max-w-3xl space-y-5"
>
    @csrf
    @if ($palette->exists)
        @method('PUT')
    @endif

    <div class="grid grid-cols-3 gap-4">
        <div class="col-span-2">
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Nom</label>
            <input
                type="text"
                name="name"
                value="{{ old('name', $palette->name) }}"
                required
                class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                placeholder="Bois naturels"
            >
        </div>
        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Position</label>
            <input
                type="number"
                name="sort_order"
                value="{{ old('sort_order', $palette->sort_order ?? 0) }}"
                min="0"
                required
                class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            >
        </div>
    </div>

    <div>
        <label class="font-sans-soft text-sm text-foreground block mb-1.5">Description (facultatif)</label>
        <input
            type="text"
            name="description"
            value="{{ old('description', $palette->description) }}"
            maxlength="255"
            class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            placeholder="Essences non teintées, tons bruns"
        >
    </div>

    <div>
        @php $selectedIds = array_map('intval', (array) old('wood_texture_ids', $palette->woodTextures->pluck('id')->all())); @endphp

        <p class="font-sans-soft text-sm text-foreground mb-1">Essences</p>
        <p class="font-sans-soft text-xs text-muted-foreground mb-3">Au moins {{ \App\Models\Palette::MIN_ESSENCES }}. Le code grille s'affiche dans les cases du plan de montage.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            @foreach ($woodTextures as $texture)
                <label class="flex items-center gap-3 px-3 py-2 bg-card border border-border rounded-xl cursor-pointer hover:bg-muted/40 transition has-[:checked]:border-foreground {{ $texture->is_active ? '' : 'opacity-60' }}">
                    <input
                        type="checkbox"
                        name="wood_texture_ids[]"
                        value="{{ $texture->id }}"
                        class="rounded border-border"
                        {{ in_array($texture->id, $selectedIds, true) ? 'checked' : '' }}
                    >
                    <span class="w-7 h-7 rounded-lg border border-border shrink-0" style="background-color: {{ $texture->hex }};"></span>
                    <span class="min-w-0">
                        <span class="block font-sans-soft text-sm text-foreground truncate">{{ $texture->name }}</span>
                        <span class="block font-sans-soft text-xs text-muted-foreground font-mono">
                            {{ $texture->grid_code ?: '—' }} · {{ $texture->hex }}{{ $texture->is_active ? '' : ' · inactive' }}
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-2 pt-1">
        <input type="checkbox" name="is_active" id="is_active" value="1" class="rounded border-border"
            {{ old('is_active', $palette->is_active ?? true) ? 'checked' : '' }}>
        <label for="is_active" class="font-sans-soft text-sm text-foreground">Disponible dans l'outil Fragment</label>
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="px-6 py-2.5 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition">
            {{ $palette->exists ? 'Enregistrer' : 'Créer' }}
        </button>
        <a href="{{ route('admin.palettes.index') }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
            Annuler
        </a>
    </div>
</form>
@endsection
