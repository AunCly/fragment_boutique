@extends('layouts.admin')

@section('title', $format->exists ? 'Modifier le format' : 'Nouveau format')

@section('content')
<div class="mb-8">
    <a href="{{ route('admin.formats.index') }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
        ← Formats
    </a>
    <h1 class="font-serif text-3xl text-foreground mt-3">
        {{ $format->exists ? 'Modifier le format' : 'Nouveau format' }}
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
    action="{{ $format->exists ? route('admin.formats.update', $format) : route('admin.formats.store') }}"
    class="max-w-xl space-y-5"
>
    @csrf
    @if ($format->exists)
        @method('PUT')
    @endif

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Label affiché</label>
            <input
                type="text"
                name="label"
                value="{{ old('label', $format->label) }}"
                required
                class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                placeholder="32 × 32 cm"
            >
        </div>
        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Slug (identifiant unique)</label>
            <input
                type="text"
                name="slug"
                value="{{ old('slug', $format->slug) }}"
                required
                class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm font-mono text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                placeholder="square-l"
            >
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Colonnes (largeur cm)</label>
            <input
                type="number"
                name="cols"
                value="{{ old('cols', $format->cols) }}"
                min="1" max="500"
                required
                class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                placeholder="32"
            >
        </div>
        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Lignes (hauteur cm)</label>
            <input
                type="number"
                name="rows"
                value="{{ old('rows', $format->rows) }}"
                min="1" max="500"
                required
                class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                placeholder="32"
            >
        </div>
        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Position</label>
            <input
                type="number"
                name="sort_order"
                value="{{ old('sort_order', $format->sort_order ?? 0) }}"
                min="0"
                required
                class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            >
        </div>
    </div>

    <div class="flex items-center gap-2">
        <input
            type="checkbox"
            name="is_active"
            id="is_active"
            value="1"
            class="rounded border-border"
            {{ old('is_active', $format->is_active ?? true) ? 'checked' : '' }}
        >
        <label for="is_active" class="font-sans-soft text-sm text-foreground">Visible dans le builder</label>
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button
            type="submit"
            class="px-6 py-2.5 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition"
        >
            {{ $format->exists ? 'Enregistrer' : 'Créer' }}
        </button>
        <a href="{{ route('admin.formats.index') }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
            Annuler
        </a>
    </div>
</form>
@endsection
