@extends('layouts.admin')

@section('title', 'Projets de l\'outil')

@section('main_class', 'max-w-[1600px]')

@section('content')
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="font-serif text-3xl text-foreground">Projets</h1>
        <p class="font-sans-soft text-sm text-muted-foreground mt-1">{{ $projects->total() }} projet{{ $projects->total() !== 1 ? 's' : '' }} · partagés entre tous les admins</p>
    </div>
    <a href="{{ route('admin.tool.index') }}" class="px-5 py-2.5 bg-primary text-background font-sans-soft text-sm uppercase hover:opacity-90 transition">
        + Nouveau projet
    </a>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
        <p class="font-sans-soft text-sm text-green-700">{{ session('success') }}</p>
    </div>
@endif

@if ($projects->isEmpty())
    <div class="text-center py-20 border border-border rounded-2xl bg-card">
        <p class="font-sans-soft text-muted-foreground">Aucun projet pour l'instant : ils sont créés à la première génération (hors mode test).</p>
    </div>
@else
    <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($projects as $project)
            <div class="border border-border rounded-2xl bg-card p-3 flex flex-col gap-3" data-project="{{ $project->id }}">
                <a href="{{ route('admin.tool.projects.show', $project) }}" class="grid grid-cols-2 gap-2" title="Ouvrir le projet">
                    <span class="aspect-square rounded-lg overflow-hidden bg-muted border border-border">
                        <img src="{{ route('admin.tool.projects.thumbnail', $project) }}" alt="Image source" loading="lazy" class="w-full h-full object-cover">
                    </span>
                    <span class="aspect-square rounded-lg overflow-hidden bg-muted border border-border flex items-center justify-center">
                        @if ($project->selected_version_id)
                            <img src="{{ route('admin.tool.projects.preview', $project) }}?v={{ $project->updated_at?->timestamp }}" alt="Résultat en aplats" loading="lazy" class="max-w-full max-h-full [image-rendering:pixelated]">
                        @else
                            <span class="font-sans-soft text-[11px] text-muted-foreground px-2 text-center">Aucune version choisie</span>
                        @endif
                    </span>
                </a>

                <div class="min-w-0">
                    <a href="{{ route('admin.tool.projects.show', $project) }}" class="block font-sans-soft text-sm font-medium text-foreground truncate hover:underline">{{ $project->name }}</a>
                    <p class="font-sans-soft text-xs text-muted-foreground mt-0.5">
                        {{ $project->updated_at?->format('d/m/Y H:i') }} · {{ $project->palette?->name }}@unless ($project->palette?->is_active) (archivée)@endunless
                    </p>
                    <p class="font-sans-soft text-xs text-muted-foreground">
                        {{ $project->format?->cols }} × {{ $project->format?->rows }} · {{ $project->versions_count }} version{{ $project->versions_count !== 1 ? 's' : '' }} · <span class="font-mono">{{ number_format($project->cost_usd, 4) }} $</span>
                    </p>
                </div>

                <div class="flex items-center justify-between mt-auto">
                    <a href="{{ route('admin.tool.projects.show', $project) }}" class="font-sans-soft text-sm text-foreground hover:underline">Ouvrir</a>
                    <form method="POST" action="{{ route('admin.tool.projects.destroy', $project) }}" onsubmit="return confirm('Supprimer définitivement ce projet et toutes ses versions ?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-sans-soft text-sm text-muted-foreground hover:text-destructive transition">Supprimer</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8">
        {{ $projects->links() }}
    </div>
@endif
@endsection
