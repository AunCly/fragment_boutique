@extends('layouts.admin')

@section('title', $faq->exists ? 'Modifier la question' : 'Nouvelle question')

@section('content')
<div class="mb-8">
    <a href="{{ route('admin.faqs.index') }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
        ← FAQ
    </a>
    <h1 class="font-serif text-3xl text-foreground mt-3">
        {{ $faq->exists ? 'Modifier la question' : 'Nouvelle question' }}
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
    action="{{ $faq->exists ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}"
    class="max-w-2xl space-y-5"
>
    @csrf
    @if ($faq->exists)
        @method('PUT')
    @endif

    <div>
        <label class="font-sans-soft text-sm text-foreground block mb-1.5">Question</label>
        <input
            type="text"
            name="question"
            value="{{ old('question', $faq->question) }}"
            required
            class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            placeholder="Quelle est votre question ?"
        >
    </div>

    <div>
        <label class="font-sans-soft text-sm text-foreground block mb-1.5">Réponse</label>
        <textarea
            name="answer"
            rows="5"
            required
            class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition resize-none"
            placeholder="Votre réponse..."
        >{{ old('answer', $faq->answer) }}</textarea>
    </div>

    <div class="flex items-end gap-6">
        <div class="w-32">
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Position</label>
            <input
                type="number"
                name="position"
                value="{{ old('position', $faq->position ?? 0) }}"
                min="0"
                required
                class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            >
        </div>

        <div class="flex items-center gap-2 pb-3">
            <input
                type="checkbox"
                name="is_active"
                id="is_active"
                value="1"
                class="rounded border-border"
                {{ old('is_active', $faq->is_active ?? true) ? 'checked' : '' }}
            >
            <label for="is_active" class="font-sans-soft text-sm text-foreground">Visible sur le site</label>
        </div>
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button
            type="submit"
            class="px-6 py-2.5 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition"
        >
            {{ $faq->exists ? 'Enregistrer' : 'Créer' }}
        </button>
        <a href="{{ route('admin.faqs.index') }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
            Annuler
        </a>
    </div>
</form>
@endsection
