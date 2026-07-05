@extends('layouts.admin')

@section('title', 'Formats')

@section('content')
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="font-serif text-3xl text-foreground">Formats</h1>
        <p class="font-sans-soft text-sm text-muted-foreground mt-1">{{ $formats->count() }} format{{ $formats->count() !== 1 ? 's' : '' }}</p>
    </div>
    <a href="{{ route('admin.formats.create') }}" class="px-5 py-2.5 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition">
        + Ajouter
    </a>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
        <p class="font-sans-soft text-sm text-green-700">{{ session('success') }}</p>
    </div>
@endif

@if ($formats->isEmpty())
    <div class="text-center py-20 border border-border rounded-2xl bg-card">
        <p class="font-sans-soft text-muted-foreground">Aucun format pour l'instant.</p>
    </div>
@else
    <div class="space-y-2">
        @foreach ($formats as $format)
            <div class="border border-border rounded-2xl bg-card px-5 py-4 flex items-center gap-4">
                <span class="font-sans-soft text-xs text-muted-foreground w-6 text-center shrink-0">{{ $format->sort_order }}</span>

                {{-- Shape icon --}}
                <div class="shrink-0 flex items-center justify-center w-10 h-10">
                    @php
                        $w = $format->cols >= $format->rows ? 36 : round(36 * $format->cols / $format->rows);
                        $h = $format->rows >= $format->cols ? 36 : round(36 * $format->rows / $format->cols);
                    @endphp
                    <div class="rounded border-2 border-foreground/40 bg-muted" style="width: {{ $w }}px; height: {{ $h }}px;"></div>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="font-sans-soft text-sm font-medium text-foreground">{{ $format->label }}</p>
                    <p class="font-sans-soft text-xs text-muted-foreground mt-0.5 font-mono">{{ $format->cols }} × {{ $format->rows }} · {{ $format->shape }} · {{ $format->slug }}</p>
                </div>

                <span class="font-sans-soft text-xs px-2 py-0.5 rounded-full border shrink-0 {{ $format->is_active ? 'text-green-700 bg-green-50 border-green-200' : 'text-muted-foreground bg-muted border-border' }}">
                    {{ $format->is_active ? 'Actif' : 'Inactif' }}
                </span>

                <div class="flex items-center gap-3 shrink-0">
                    <a href="{{ route('admin.formats.edit', $format) }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
                        Modifier
                    </a>
                    <form method="POST" action="{{ route('admin.formats.destroy', $format) }}" onsubmit="return confirm('Supprimer ce format ?')">
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
