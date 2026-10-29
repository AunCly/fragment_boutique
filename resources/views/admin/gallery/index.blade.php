@extends('layouts.admin')

@section('title', 'Galerie')

@section('content')
<div class="mb-8">
    <h1 class="font-serif text-3xl text-foreground">Galerie</h1>
    <p class="font-sans-soft text-sm text-muted-foreground mt-1">{{ $images->count() }} image{{ $images->count() !== 1 ? 's' : '' }}</p>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
        <p class="font-sans-soft text-sm text-green-700">{{ session('success') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
        @foreach ($errors->all() as $error)
            <p class="font-sans-soft text-sm text-red-600">{{ $error }}</p>
        @endforeach
    </div>
@endif

<form
    method="POST"
    action="{{ route('admin.gallery-images.store') }}"
    enctype="multipart/form-data"
    class="mb-10 p-5 border border-border rounded-2xl bg-card space-y-4"
>
    @csrf

    <div>
        <label class="font-sans-soft text-sm text-foreground block mb-1.5">Ajouter des images</label>
        <label class="flex items-center gap-3 px-4 py-3 bg-card border border-border border-dashed rounded-xl cursor-pointer hover:bg-muted/40 transition">
            <span class="font-sans-soft text-sm text-muted-foreground" id="upload-text">Choisir une ou plusieurs images…</span>
            <input
                type="file"
                name="images[]"
                accept="image/*"
                multiple
                required
                class="sr-only"
                id="images-input"
            >
        </label>
        <p class="font-sans-soft text-xs text-muted-foreground mt-1.5">5 Mo maximum par image, 20 images par envoi.</p>
    </div>

    <div>
        <label class="font-sans-soft text-sm text-foreground block mb-1.5">Texte alternatif (optionnel)</label>
        <input
            type="text"
            name="alt"
            value="{{ old('alt') }}"
            maxlength="255"
            class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            placeholder="{{ \App\Models\GalleryImage::DEFAULT_ALT }}"
        >
    </div>

    <button type="submit" class="px-6 py-2.5 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition">
        Uploader
    </button>
</form>

@if ($images->isEmpty())
    <div class="text-center py-20 border border-border rounded-2xl bg-card">
        <p class="font-sans-soft text-muted-foreground">Aucune image pour l'instant.</p>
    </div>
@else
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        @foreach ($images as $image)
            <div class="border border-border rounded-2xl bg-card overflow-hidden flex flex-col">
                <img src="{{ $image->url }}" alt="{{ $image->alt_text }}" class="w-full aspect-square object-cover" loading="lazy">

                <form method="POST" action="{{ route('admin.gallery-images.update', $image) }}" class="p-3 space-y-2">
                    @csrf
                    @method('PUT')
                    <input
                        type="text"
                        name="alt"
                        value="{{ $image->alt }}"
                        maxlength="255"
                        class="w-full px-3 py-2 bg-card border border-border rounded-lg font-sans-soft text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                        placeholder="Texte alternatif"
                    >
                    <div class="flex items-center gap-2">
                        <input
                            type="number"
                            name="sort_order"
                            value="{{ $image->sort_order }}"
                            min="0"
                            required
                            title="Position"
                            class="w-20 px-3 py-2 bg-card border border-border rounded-lg font-sans-soft text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                        >
                        <button type="submit" class="font-sans-soft text-xs text-muted-foreground hover:text-foreground transition">
                            Enregistrer
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.gallery-images.destroy', $image) }}" onsubmit="return confirm('Supprimer cette image ?')" class="px-3 pb-3 mt-auto">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="font-sans-soft text-xs text-muted-foreground hover:text-destructive transition">
                        Supprimer
                    </button>
                </form>
            </div>
        @endforeach
    </div>
@endif

<script>
    document.getElementById('images-input').addEventListener('change', function () {
        const count = this.files.length;
        document.getElementById('upload-text').textContent = count === 0
            ? 'Choisir une ou plusieurs images…'
            : (count === 1 ? this.files[0].name : count + ' images sélectionnées');
    });
</script>
@endsection
