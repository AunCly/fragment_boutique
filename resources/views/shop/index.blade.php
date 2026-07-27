@extends('layouts.shop')

@section('main-class', 'w-full')

@section('content')
    <section class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <div class="grid grid-cols-1 md:grid-cols-[40%_60%] gap-12 md:gap-16 items-center">
            <div>
                <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
                    Atelier français · Tableaux en bois à assembler
                </p>

                <h1 class="font-serif font-normal text-7xl leading-none text-foreground mt-4">
                    Un objet décoratif
                    <br>
                    <span class="text-primary italic">créé par vous.</span>
                </h1>

                <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
                    Choisissez un tableau dans notre collection. Vous recevez toutes les pièces de bois, prêtes à être assemblées chez vous. Le résultat : un objet décoratif que vous avez fabriqué de vos mains, à afficher chez vous.
                </p>

                <a
                    href="{{ route('shop.products') }}"
                    class="inline-flex items-center gap-2 mt-8 px-8 py-4 bg-primary text-background font-sans-soft text-sm uppercase hover:opacity-90 transition"
                >
                    Découvrir la collection
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14"/>
                        <path d="m13 6 6 6-6 6"/>
                    </svg>
                </a>

                <div class="mt-6">
                    <a
                        href="{{ route('shop.custom') }}"
                        class="relative inline-block font-sans-soft text-sm uppercase tracking-wide text-foreground after:content-[''] after:absolute after:left-0 after:-bottom-1 after:h-px after:w-full after:origin-left after:scale-x-0 after:bg-primary after:transition-transform after:duration-500 hover:after:scale-x-100"
                    >
                        Créer votre propre Fragment
                    </a>
                </div>
            </div>

            <div>
                <img
                    src="{{ Vite::asset('resources/images/hero-evoli-room.png') }}"
                    alt="Tableau en bois Fragment assemblé, installé dans un intérieur"
                    class="w-full h-auto rounded-lg"
                >
            </div>
        </div>
    </section>
@endsection
