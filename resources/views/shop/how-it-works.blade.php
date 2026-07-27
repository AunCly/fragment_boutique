@extends('layouts.shop')

@section('main-class', 'w-full')

@section('content')
    <section class="max-w-2xl mx-auto px-4 py-16 md:py-16 text-center">
        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
            Comment ça marche
        </p>

        <h2 class="font-serif font-normal text-5xl md:text-6xl leading-snug text-foreground mt-4">
            Choisir, recevoir, assembler, accrocher.
        </h2>

        <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
            Fragment tient en quatre étapes simples. Regardez la vidéo pour voir comment ça se passe, puis découvrez chaque étape en détail plus bas.
        </p>
    </section>

    <section class="max-w-3xl mx-auto px-4 pb-16 md:pb-24 text-center">
        <div class="aspect-video rounded-lg overflow-hidden">
            <iframe
                src="https://www.youtube.com/embed/lAg7a4Zg0xI"
                title="Fragment expliqué en une minute"
                class="w-full h-full"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
            ></iframe>
        </div>

        <p class="font-sans-soft text-sm text-muted-foreground mt-4">
            Fragment expliqué en une minute
        </p>
    </section>

    <section class="max-w-6xl mx-auto px-4 pb-16 md:pb-24">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-12">
            <div class="border-t border-[#dbd3c6] pt-8">
                <span class="font-serif text-4xl text-primary">01</span>
                <h3 class="font-serif text-2xl text-foreground mt-4">Choisissez votre tableau</h3>
                <p class="font-sans-soft text-foreground/80 text-sm mt-2 leading-relaxed">Parcourez la collection et sélectionnez le motif et le format qui vous plaisent. Ou envoyez votre propre image pour un tableau sur-mesure.</p>
            </div>

            <div class="border-t border-[#dbd3c6] pt-8">
                <span class="font-serif text-4xl text-primary">02</span>
                <h3 class="font-serif text-2xl text-foreground mt-4">Recevez le kit chez vous</h3>
                <p class="font-sans-soft text-foreground/80 text-sm mt-2 leading-relaxed">Le colis contient tout ce dont vous avez besoin : le cadre en bois massif, toutes les pièces de bois triées par teinte, une pince en bois et le plan de montage illustré.</p>
            </div>

            <div class="border-t border-[#dbd3c6] pt-8">
                <span class="font-serif text-4xl text-primary">03</span>
                <h3 class="font-serif text-2xl text-foreground mt-4">Assemblez à votre rythme</h3>
                <p class="font-sans-soft text-foreground/80 text-sm mt-2 leading-relaxed">Placez chaque pièce sur le cadre en suivant le plan. Comptez une à trois soirées. Aucun outil, aucune compétence, aucune colle apparente.</p>
            </div>

            <div class="border-t border-[#dbd3c6] pt-8">
                <span class="font-serif text-4xl text-primary">04</span>
                <h3 class="font-serif text-2xl text-foreground mt-4">Accrochez chez vous</h3>
                <p class="font-sans-soft text-foreground/80 text-sm mt-2 leading-relaxed">Votre tableau est prêt. Vous l'avez fabriqué de vos mains — il devient un objet décoratif unique dans votre intérieur.</p>
            </div>
        </div>
    </section>

    <section class="bg-[#f9f5ec] py-16 md:py-24">
        <div class="max-w-6xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 md:gap-16 items-center">
                <div>
                    <img
                        src="{{ Vite::asset('resources/images/atelier-hands.jpg') }}"
                        alt="Mains façonnant un tableau en bois Fragment dans l'atelier"
                        class="w-full h-auto rounded-lg"
                    >
                </div>

                <div x-data="{ open: null }">
                    <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
                        Questions fréquentes
                    </p>

                    <h2 class="font-serif font-normal text-4xl leading-none text-foreground mt-4">
                        Les réponses aux questions qu'on nous pose souvent.
                    </h2>

                    <div class="mt-8 border-t border-foreground/15 divide-y divide-foreground/15">
                        @foreach($faqs as $faq)
                            <div>
                                <button
                                    type="button"
                                    @click="open = open === {{ $faq->id }} ? null : {{ $faq->id }}"
                                    class="w-full flex items-center justify-between gap-4 py-5 text-left"
                                >
                                    <span class="font-serif text-lg text-foreground">{{ $faq->question }}</span>
                                    <span
                                        class="shrink-0 text-2xl font-serif text-foreground"
                                        x-text="open === {{ $faq->id }} ? '−' : '+'"
                                    ></span>
                                </button>
                                <div
                                    x-show="open === {{ $faq->id }}"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 -translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 -translate-y-1"
                                    class="pb-5"
                                >
                                    <p class="font-sans-soft text-foreground/80 text-sm leading-relaxed">{{ $faq->answer }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

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
