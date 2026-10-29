@extends('layouts.shop')

@section('main-class', 'w-full')

@push('head')
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endpush

@section('content')
    <section class="max-w-2xl mx-auto px-4 py-16 md:py-16 text-center">
        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
            Contact
        </p>

        <h2 class="font-serif font-normal text-5xl md:text-6xl leading-snug text-foreground mt-4">
            Écrivez-nous,
            <br>
            <span class="italic">on vous répond.</span>
        </h2>

        <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
            Une question sur un kit, une commande ou un projet sur-mesure ? Laissez-nous un message, l'atelier vous répond sous 48 heures ouvrées.
        </p>
    </section>

    <section class="max-w-xl mx-auto px-4 pb-16 md:pb-24">
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

        <form method="POST" action="{{ route('shop.contact.send') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="form_started_at" value="{{ $formStartedAt }}">

            {{-- Honeypot: hidden from humans, filled by bots --}}
            <div class="hidden" aria-hidden="true">
                <label for="website">Ne pas remplir ce champ</label>
                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label for="name" class="font-sans-soft text-sm text-foreground block mb-1.5">Nom et prénom</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name') }}"
                    required
                    maxlength="150"
                    autocomplete="name"
                    class="w-full px-4 py-3 bg-muted border border-border font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                >
            </div>

            <div>
                <label for="email" class="font-sans-soft text-sm text-foreground block mb-1.5">Adresse email</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    required
                    maxlength="255"
                    autocomplete="email"
                    class="w-full px-4 py-3 bg-muted border border-border font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                >
            </div>

            <div>
                <label for="message" class="font-sans-soft text-sm text-foreground block mb-1.5">Message</label>
                <textarea
                    name="message"
                    id="message"
                    rows="7"
                    required
                    minlength="10"
                    maxlength="5000"
                    class="w-full px-4 py-3 bg-muted border border-border font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                >{{ old('message') }}</textarea>
            </div>

            <div
                class="cf-turnstile"
                data-sitekey="{{ config('services.turnstile.site_key') }}"
                data-language="fr"
                data-theme="light"
            ></div>

            <button
                type="submit"
                class="w-full inline-flex items-center justify-center gap-2 px-8 py-4 bg-primary text-background font-sans-soft text-sm uppercase hover:opacity-90 transition"
            >
                Envoyer
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12h14"/>
                    <path d="m13 6 6 6-6 6"/>
                </svg>
            </button>
        </form>
    </section>
@endsection
