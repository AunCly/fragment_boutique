@extends('layouts.shop')

@section('main-class', 'w-full')

@section('content')
    <section class="max-w-2xl mx-auto px-4 py-24 md:py-32 text-center" x-init="items = []">
        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
            Commande confirmée
        </p>

        <h1 class="font-serif font-normal text-5xl md:text-6xl leading-snug text-foreground mt-4">
            Merci pour votre <span class="italic">commande.</span>
        </h1>

        <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
            Votre paiement a bien été reçu. Vous allez recevoir un e-mail de confirmation avec le récapitulatif de votre commande, puis un second dès que votre colis sera expédié.
        </p>

        <div class="flex items-center justify-center gap-8 mt-10">
            <a
                href="{{ route('shop.products') }}"
                class="inline-flex items-center gap-2 px-8 py-4 bg-primary text-background font-sans-soft text-sm uppercase hover:opacity-90 transition"
            >
                Découvrir la collection
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12h14"/>
                    <path d="m13 6 6 6-6 6"/>
                </svg>
            </a>
        </div>
    </section>
@endsection
