@extends('layouts.admin')

@section('title', 'FAQ')

@section('content')
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="font-serif text-3xl text-foreground">FAQ</h1>
        <p class="font-sans-soft text-sm text-muted-foreground mt-1">{{ $faqs->count() }} question{{ $faqs->count() !== 1 ? 's' : '' }}</p>
    </div>
    <a href="{{ route('admin.faqs.create') }}" class="px-5 py-2.5 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition">
        + Ajouter
    </a>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
        <p class="font-sans-soft text-sm text-green-700">{{ session('success') }}</p>
    </div>
@endif

@if ($faqs->isEmpty())
    <div class="text-center py-20 border border-border rounded-2xl bg-card">
        <p class="font-sans-soft text-muted-foreground">Aucune question pour l'instant.</p>
        <a href="{{ route('admin.faqs.create') }}" class="mt-4 inline-block font-sans-soft text-sm text-foreground underline underline-offset-2 hover:opacity-70 transition">
            Ajouter la première question
        </a>
    </div>
@else
    <div class="space-y-2">
        @foreach ($faqs as $faq)
            <div class="border border-border rounded-2xl bg-card px-5 py-4 flex items-center gap-4">
                <span class="font-sans-soft text-xs text-muted-foreground w-6 text-center shrink-0">{{ $faq->position }}</span>

                <div class="flex-1 min-w-0">
                    <p class="font-sans-soft text-sm font-medium text-foreground truncate">{{ $faq->question }}</p>
                    <p class="font-sans-soft text-xs text-muted-foreground mt-0.5 truncate">{{ $faq->answer }}</p>
                </div>

                <span class="font-sans-soft text-xs px-2 py-0.5 rounded-full border shrink-0 {{ $faq->is_active ? 'text-green-700 bg-green-50 border-green-200' : 'text-muted-foreground bg-muted border-border' }}">
                    {{ $faq->is_active ? 'Actif' : 'Inactif' }}
                </span>

                <div class="flex items-center gap-3 shrink-0">
                    <a href="{{ route('admin.faqs.edit', $faq) }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
                        Modifier
                    </a>
                    <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" onsubmit="return confirm('Supprimer cette question ?')">
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
