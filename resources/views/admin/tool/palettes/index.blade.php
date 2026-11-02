@extends('layouts.admin')

@section('title', 'Palettes')

@section('content')
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="font-serif text-3xl text-foreground">Palettes</h1>
        <p class="font-sans-soft text-sm text-muted-foreground mt-1">{{ $palettes->count() }} palette{{ $palettes->count() !== 1 ? 's' : '' }}</p>
    </div>
    <a href="{{ route('admin.palettes.create') }}" class="px-5 py-2.5 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition">
        + Ajouter
    </a>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
        <p class="font-sans-soft text-sm text-green-700">{{ session('success') }}</p>
    </div>
@endif

@if ($palettes->isEmpty())
    <div class="text-center py-20 border border-border rounded-2xl bg-card">
        <p class="font-sans-soft text-muted-foreground">Aucune palette pour l'instant.</p>
    </div>
@else
    <div class="space-y-2">
        @foreach ($palettes as $palette)
            <div class="border border-border rounded-2xl bg-card px-5 py-4 flex items-center gap-4">
                <span class="font-sans-soft text-xs text-muted-foreground w-6 text-center shrink-0">{{ $palette->sort_order }}</span>

                <div class="flex-1 min-w-0">
                    <p class="font-sans-soft text-sm font-medium text-foreground">{{ $palette->name }}</p>
                    @if ($palette->description)
                        <p class="font-sans-soft text-xs text-muted-foreground mt-0.5 truncate">{{ $palette->description }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-1 mt-2">
                        @foreach ($palette->woodTextures as $texture)
                            <span
                                class="w-5 h-5 rounded border border-border"
                                style="background-color: {{ $texture->hex }};"
                                title="{{ $texture->grid_code }} · {{ $texture->name }}"
                            ></span>
                        @endforeach
                        <span class="font-sans-soft text-xs text-muted-foreground ml-1">
                            {{ $palette->woodTextures->count() }} essence{{ $palette->woodTextures->count() !== 1 ? 's' : '' }}
                        </span>
                    </div>
                </div>

                <span class="font-sans-soft text-xs px-2 py-0.5 rounded-full border shrink-0 {{ $palette->is_active ? 'text-green-700 bg-green-50 border-green-200' : 'text-muted-foreground bg-muted border-border' }}">
                    {{ $palette->is_active ? 'Active' : 'Inactive' }}
                </span>

                <div class="flex items-center gap-3 shrink-0">
                    <a href="{{ route('admin.palettes.edit', $palette) }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
                        Modifier
                    </a>
                    <form method="POST" action="{{ route('admin.palettes.destroy', $palette) }}" onsubmit="return confirm('Supprimer cette palette ?')">
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
