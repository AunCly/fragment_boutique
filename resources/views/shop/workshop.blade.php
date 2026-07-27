@extends('layouts.shop')

@section('main-class', 'w-full')

@section('content')
    <section class="max-w-2xl mx-auto px-4 py-16 md:py-16 text-center">
        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
            L'atelier
        </p>

        <h2 class="font-serif font-normal text-5xl md:text-6xl leading-snug text-foreground mt-4">
            Un atelier français qui <span class="italic">travaille le bois.</span>
        </h2>

        <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Suspendisse potenti, nullam ac tortor vitae purus faucibus ornare suspendisse sed nisi.
        </p>
    </section>

    <section class="max-w-6xl mx-auto px-4 pb-16 md:pb-24 text-center">
        <img
            src="{{ Vite::asset('resources/images/atelier-workshop.jpg') }}"
            alt="Atelier Fragment : découpe du bois français"
            class="w-full h-auto rounded-lg"
        >
    </section>

    <section class="max-w-6xl mx-auto px-4 pb-16 md:pb-24">
        <div class="grid grid-cols-1 md:grid-cols-[40%_60%] gap-12 md:gap-16 items-center">
            <div>
                <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
                    La matière
                </p>

                <h2 class="font-serif font-normal text-4xl leading-none text-foreground mt-4">
                    Du bois français, choisi pour ses couleurs.
                </h2>

                <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
                    Nous travaillons principalement avec des essences françaises : chêne clair, hêtre blond, noyer profond, frêne teinté. Chaque essence a sa couleur et son grain propres — c'est ce qui donne du relief à chaque tableau, une fois assemblé.
                </p>
            </div>

            <div>
                <img
                    src="{{ Vite::asset('resources/images/atelier-wood.jpg') }}"
                    alt="Essences de bois français utilisées pour les tableaux Fragment"
                    class="w-full aspect-[3/2] object-cover rounded-lg"
                >
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

                <div>
                    <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
                        La fabrication
                    </p>

                    <h2 class="font-serif font-normal text-4xl leading-none text-foreground mt-4">
                        Chaque kit est préparé à la main.
                    </h2>

                    <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
                        Chaque pièce de bois est découpée au laser, poncée, puis triée par couleur à la main. Nous vérifions chaque kit avant de l'envoyer : le cadre, toutes les pièces, la pince et le plan de montage. Vous recevez tout ce qu'il faut pour assembler votre tableau chez vous.
                    </p>

                    <div class="grid grid-cols-3 gap-6 mt-10">
                        <div class="border-t border-[#dbd3c6] pt-4">
                            <p class="font-serif text-2xl text-primary">12+</p>
                            <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground mt-1">Essences de bois</p>
                        </div>

                        <div class="border-t border-[#dbd3c6] pt-4">
                            <p class="font-serif text-2xl text-primary">Découpe</p>
                            <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground mt-1">Laser à l'atelier</p>
                        </div>

                        <div class="border-t border-[#dbd3c6] pt-4">
                            <p class="font-serif text-2xl text-primary">100%</p>
                            <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground mt-1">Fabriqué en France</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-primary py-16 md:py-24">
        <div class="max-w-3xl mx-auto px-4 text-left">
            <p class="font-sans-soft text-xs uppercase tracking-widest text-background/70">
                La créatrice
            </p>

            <h2 class="font-serif font-normal text-3xl md:text-4xl leading-snug text-background mt-4">
                « Je voulais que chacun puisse fabriquer son propre tableau. »
            </h2>

            <div class="mt-6 space-y-4">
                <p class="font-sans-soft text-background/80 leading-relaxed">
                    J'ai lancé Fragment en 2023, dans un atelier à quelques kilomètres de Lyon. L'idée est partie d'un constat simple : acheter un tableau, c'est bien. En fabriquer un soi-même, c'est beaucoup plus gratifiant.
                </p>
                <p class="font-sans-soft text-background/80 leading-relaxed">
                    Fragment vous permet de créer un vrai objet décoratif en bois, sans avoir besoin de savoir-faire ni d'atelier. Vous choisissez un motif, on prépare tout, vous assemblez chez vous. Le résultat est à vous.
                </p>
            </div>

            <p class="font-sans-soft text-background/70 italic mt-6">
                — Camille, fondatrice de Fragment
            </p>
        </div>
    </section>

    <section class="max-w-2xl mx-auto px-4 py-16 md:py-24 text-center">
        <h2 class="font-serif font-normal text-5xl leading-none text-foreground">
            Choisissez votre tableau.
        </h2>

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

        </div>
    </section>
@endsection
