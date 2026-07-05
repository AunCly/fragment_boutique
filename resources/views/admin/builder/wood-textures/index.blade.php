@extends('layouts.admin')

@section('title', 'Essences de bois')

@section('content')
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="font-serif text-3xl text-foreground">Essences de bois</h1>
        <p class="font-sans-soft text-sm text-muted-foreground mt-1">{{ $textures->count() }} essence{{ $textures->count() !== 1 ? 's' : '' }}</p>
    </div>
    <a href="{{ route('admin.wood-textures.create') }}" class="px-5 py-2.5 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition">
        + Ajouter
    </a>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
        <p class="font-sans-soft text-sm text-green-700">{{ session('success') }}</p>
    </div>
@endif

@if ($textures->isEmpty())
    <div class="text-center py-20 border border-border rounded-2xl bg-card">
        <p class="font-sans-soft text-muted-foreground">Aucune essence pour l'instant.</p>
    </div>
@else
    <div class="space-y-2">
        @foreach ($textures as $texture)
            <div class="border border-border rounded-2xl bg-card px-5 py-3 flex items-center gap-4">
                <span class="font-sans-soft text-xs text-muted-foreground w-6 text-center shrink-0">{{ $texture->sort_order }}</span>

                {{-- Texture preview --}}
                <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0 border border-border">
                    @if ($texture->texture_url)
                        <img src="{{ $texture->texture_url }}" alt="{{ $texture->name }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full" style="background-color: {{ $texture->hex }}"></div>
                    @endif
                </div>

                {{-- Hex swatch --}}
                <div class="w-5 h-5 rounded-full border border-border shrink-0" style="background-color: {{ $texture->hex }}"></div>

                <div class="flex-1 min-w-0">
                    <p class="font-sans-soft text-sm font-medium text-foreground">{{ $texture->name }}</p>
                    <p class="font-sans-soft text-xs text-muted-foreground mt-0.5">{{ implode(', ', $texture->tags ?? []) }}</p>
                </div>

                <span class="font-sans-soft text-xs font-mono text-muted-foreground shrink-0">{{ $texture->grid_code }}</span>

                <span class="font-sans-soft text-xs px-2 py-0.5 rounded-full border shrink-0 {{ $texture->is_active ? 'text-green-700 bg-green-50 border-green-200' : 'text-muted-foreground bg-muted border-border' }}">
                    {{ $texture->is_active ? 'Actif' : 'Inactif' }}
                </span>

                <div class="flex items-center gap-3 shrink-0">
                    <a href="{{ route('admin.wood-textures.edit', $texture) }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
                        Modifier
                    </a>
                    <form method="POST" action="{{ route('admin.wood-textures.destroy', $texture) }}" onsubmit="return confirm('Supprimer cette essence ?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-sans-soft text-sm text-muted-foreground hover:text-destructive transition">
                            Supprimer
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
