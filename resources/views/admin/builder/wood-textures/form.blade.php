@extends('layouts.admin')

@section('title', $texture->exists ? 'Modifier l\'essence' : 'Nouvelle essence')

@section('content')
<div class="mb-8">
    <a href="{{ route('admin.wood-textures.index') }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
        ← Essences de bois
    </a>
    <h1 class="font-serif text-3xl text-foreground mt-3">
        {{ $texture->exists ? 'Modifier l\'essence' : 'Nouvelle essence' }}
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
    action="{{ $texture->exists ? route('admin.wood-textures.update', $texture) : route('admin.wood-textures.store') }}"
    enctype="multipart/form-data"
    class="max-w-2xl space-y-5"
>
    @csrf
    @if ($texture->exists)
        @method('PUT')
    @endif

    <div>
        <label class="font-sans-soft text-sm text-foreground block mb-1.5">Nom</label>
        <input
            type="text"
            name="name"
            value="{{ old('name', $texture->name) }}"
            required
            class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            placeholder="Chêne Fumé"
        >
    </div>

    <div>
        <label class="font-sans-soft text-sm text-foreground block mb-1.5">
            Image {{ $texture->exists ? '(laisser vide pour conserver l\'image actuelle)' : '' }}
        </label>

        @if ($texture->exists && $texture->texture_url)
            <div class="mb-3 flex items-center gap-3">
                <img src="{{ $texture->texture_url }}" alt="{{ $texture->name }}" class="w-20 h-20 rounded-xl object-cover border border-border">
                <p class="font-sans-soft text-xs text-muted-foreground">Image actuelle</p>
            </div>
        @endif

        <label class="flex items-center gap-3 px-4 py-3 bg-card border border-border border-dashed rounded-xl cursor-pointer hover:bg-muted/40 transition" id="upload-label">
            <span class="font-sans-soft text-sm text-muted-foreground" id="upload-text">
                {{ $texture->exists ? 'Choisir une nouvelle image…' : 'Choisir une image…' }}
            </span>
            <input
                type="file"
                name="texture"
                accept="image/*"
                class="sr-only"
                id="texture-input"
                {{ !$texture->exists ? 'required' : '' }}
            >
        </label>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Couleur hex</label>
            <div class="flex items-center gap-2">
                <input
                    type="color"
                    name="hex"
                    value="{{ old('hex', $texture->hex ?? '#b08a5e') }}"
                    class="w-10 h-10 rounded-lg border border-border cursor-pointer"
                >
                <input
                    type="text"
                    id="hex_text"
                    value="{{ old('hex', $texture->hex ?? '#b08a5e') }}"
                    class="flex-1 px-3 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm font-mono text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                    placeholder="#d4ac7b"
                >
            </div>
        </div>
        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Code grille</label>
            <input
                type="text"
                name="grid_code"
                value="{{ old('grid_code', $texture->grid_code) }}"
                maxlength="4"
                class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm font-mono text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                placeholder="A"
            >
        </div>
        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Position</label>
            <input
                type="number"
                name="sort_order"
                value="{{ old('sort_order', $texture->sort_order ?? 0) }}"
                min="0"
                required
                class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            >
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        @php $currentTags = old('tags', $texture->tags ?? []); @endphp

        <div>
            <p class="font-sans-soft text-sm text-foreground mb-2">Origine</p>
            <div class="space-y-2">
                @foreach (['Bois français', 'Bois exotique'] as $tag)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="tags[]" value="{{ $tag }}" class="rounded border-border"
                            {{ in_array($tag, (array) $currentTags) ? 'checked' : '' }}>
                        <span class="font-sans-soft text-sm text-foreground">{{ $tag }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <p class="font-sans-soft text-sm text-foreground mb-2">Couleur</p>
            <div class="space-y-2">
                @foreach (['Couleur naturelle', 'Couleur teintée'] as $tag)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="tags[]" value="{{ $tag }}" class="rounded border-border"
                            {{ in_array($tag, (array) $currentTags) ? 'checked' : '' }}>
                        <span class="font-sans-soft text-sm text-foreground">{{ $tag }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex items-center gap-2 pt-1">
        <input type="checkbox" name="is_active" id="is_active" value="1" class="rounded border-border"
            {{ old('is_active', $texture->is_active ?? true) ? 'checked' : '' }}>
        <label for="is_active" class="font-sans-soft text-sm text-foreground">Visible dans le builder</label>
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="px-6 py-2.5 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition">
            {{ $texture->exists ? 'Enregistrer' : 'Créer' }}
        </button>
        <a href="{{ route('admin.wood-textures.index') }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
            Annuler
        </a>
    </div>
</form>

<script>
    const colorPicker = document.querySelector('input[type="color"]');
    const hexText = document.getElementById('hex_text');
    colorPicker.addEventListener('input', () => { hexText.value = colorPicker.value; });
    hexText.addEventListener('input', () => {
        if (/^#[0-9a-fA-F]{6}$/.test(hexText.value)) colorPicker.value = hexText.value;
    });
    hexText.form.addEventListener('submit', () => { colorPicker.value = hexText.value; });

    document.getElementById('texture-input').addEventListener('change', function () {
        const name = this.files[0]?.name ?? '{{ $texture->exists ? 'Choisir une nouvelle image…' : 'Choisir une image…' }}';
        document.getElementById('upload-text').textContent = name;
    });
</script>
@endsection
