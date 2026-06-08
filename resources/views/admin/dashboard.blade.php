@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="mb-8">
    <h1 class="font-serif text-3xl text-foreground">Bonjour, {{ Auth::user()->name }}</h1>
    <p class="font-sans-soft text-sm text-muted-foreground mt-1">Espace d'administration Fragment</p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <a href="{{ route('admin.faqs.index') }}" class="border border-border rounded-2xl bg-card p-6 hover:bg-muted/50 transition block">
        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground mb-2">FAQ</p>
        <p class="font-serif text-2xl text-foreground">{{ $faqCount }}</p>
        <p class="font-sans-soft text-sm text-muted-foreground mt-1">Questions / réponses</p>
    </a>

    <div class="border border-border rounded-2xl bg-card p-6">
        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground mb-2">Bois</p>
        <p class="font-serif text-2xl text-foreground">—</p>
        <p class="font-sans-soft text-sm text-muted-foreground mt-1">Images pour le builder</p>
    </div>

    <div class="border border-border rounded-2xl bg-card p-6">
        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground mb-2">Formats</p>
        <p class="font-serif text-2xl text-foreground">—</p>
        <p class="font-sans-soft text-sm text-muted-foreground mt-1">Dimensions disponibles</p>
    </div>
</div>
@endsection
