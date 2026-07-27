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

    <section class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <div class="grid grid-cols-1 md:grid-cols-[60%_40%] gap-12 md:gap-16 items-end">
            <div>
                <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
                    Collection
                </p>

                <h2 class="font-serif font-normal text-3xl leading-none text-foreground mt-4">
                    Nos Fragments
                </h2>

                <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
                    Chaque Fragment est un tableau en bois à assembler. Choisissez un motif, un format, et recevez tout ce qu'il faut pour le monter chez vous.
                </p>
            </div>

            <div class="md:text-right">
                <a
                    href="{{ route('shop.products') }}"
                    class="relative inline-block font-sans-soft text-sm text-foreground after:content-[''] after:absolute after:left-0 after:-bottom-1 after:h-px after:w-full after:origin-left after:scale-x-0 after:bg-primary after:transition-transform after:duration-500 hover:after:scale-x-100"
                >
                    Voir toute la collection
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mt-12">
            @foreach($fragments as $fragment)
                <div class="group">
                    @if($fragment['image'])
                        <div class="w-full aspect-square rounded-lg overflow-hidden">
                            <img src="{{ $fragment['image'] }}" alt="{{ $fragment['imageAlt'] }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                        </div>
                    @else
                        <div class="w-full aspect-square bg-muted rounded-lg flex items-center justify-center text-muted-foreground font-sans-soft text-sm">Pas d'image</div>
                    @endif
                    <div class="flex items-start justify-between mt-4">
                        <div>
                            <p class="font-serif text-foreground">{{ $fragment['title'] }}</p>
                            <p class="font-sans-soft text-xs text-muted-foreground mt-1">{{ $fragment['category'] }}</p>
                        </div>
                        <p class="font-sans-soft text-sm text-foreground">{{ number_format((float) $fragment['price'], 2, ',', ' ') }} €</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <div class="grid grid-cols-1 md:grid-cols-[60%_40%] gap-12 md:gap-16 md:items-stretch">
            <div>
                <img
                    src="{{ Vite::asset('resources/images/before-after.jpg') }}"
                    alt="Avant / après : une photo transformée en tableau en bois Fragment"
                    class="w-full h-full object-cover rounded-lg"
                >
            </div>

            <div>
                <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
                    Sur-mesure
                </p>

                <h2 class="font-serif font-normal text-4xl leading-none text-foreground mt-4">
                    Créez votre propre Fragment.
                </h2>

                <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
                    Une variante de la collection : au lieu de choisir un motif existant, vous envoyez votre propre image (une photo, un dessin, un portrait) et nous la transformons en tableau de bois à assembler. Vous voyez l'aperçu avant de commander.
                </p>

                <a
                    href="{{ route('shop.custom') }}"
                    class="inline-flex items-center gap-2 mt-8 px-8 py-4 bg-primary text-background font-sans-soft text-sm uppercase hover:opacity-90 transition"
                >
                    Créer mon Fragment
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14"/>
                        <path d="m13 6 6 6-6 6"/>
                    </svg>
                </a>
            </div>
        </div>
    </section>
@endsection
