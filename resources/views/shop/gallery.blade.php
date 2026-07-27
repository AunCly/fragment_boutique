@extends('layouts.shop')

@section('main-class', 'w-full')

@section('content')
    <section class="max-w-2xl mx-auto px-4 py-16 md:py-16 text-center">
        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
            Galerie
        </p>

        <h2 class="font-serif font-normal text-5xl md:text-6xl leading-snug text-foreground mt-4">
            Des Fragments,
            <br>
            <span class="italic">chez vous.</span>
        </h2>

        <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
            Les tableaux Fragment, une fois assemblés et accrochés par leurs propriétaires.
        </p>
    </section>

    <section class="max-w-6xl mx-auto px-4 pb-16 md:pb-24">
        <div class="columns-1 sm:columns-2 lg:columns-4 gap-6">
            @foreach($images as $image)
                <div class="mb-6 break-inside-avoid">
                    <img
                        src="{{ Vite::asset($image['path']) }}"
                        alt="{{ $image['alt'] }}"
                        class="w-full h-auto rounded-lg"
                    >
                </div>
            @endforeach
        </div>
    </section>

    <div class="max-w-6xl mx-auto px-4">
        <div class="border-t border-[#dbd3c6]"></div>
    </div>

    <section class="max-w-2xl mx-auto px-4 py-16 md:py-24 text-center">
        <h2 class="font-serif font-normal text-5xl leading-none text-foreground">
            Prêt à créer le vôtre ?
        </h2>

        <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
            Un tableau en bois, assemblé par vous, fait pour rester chez vous longtemps.
        </p>

        <div class="flex items-center justify-center gap-8 mt-8">
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

            <a
                href="{{ route('shop.custom') }}"
                class="relative inline-flex items-center gap-1 font-sans-soft text-sm text-foreground after:content-[''] after:absolute after:left-0 after:-bottom-1 after:h-px after:w-full after:origin-left after:scale-x-0 after:bg-primary after:transition-transform after:duration-500 hover:after:scale-x-100"
            >
                Créer mon fragment
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12h14"/>
                    <path d="m13 6 6 6-6 6"/>
                </svg>
            </a>
        </div>
    </section>
@endsection
