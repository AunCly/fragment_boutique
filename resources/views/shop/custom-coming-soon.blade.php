@extends('layouts.shop')

@section('main-class', 'w-full')

@section('content')
    <section class="max-w-2xl mx-auto px-4 py-16 md:py-16 text-center">
        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
            Sur-mesure · Bientôt disponible
        </p>

        <h2 class="font-serif font-normal text-5xl md:text-6xl leading-snug text-foreground mt-4">
            Votre image, votre Fragment.
        </h2>

        <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
            Le créateur Fragment vous permettra de transformer votre propre image (une photo, un dessin, un portrait) en tableau de bois à assembler. Nous travaillons dur pour vous le proposer très bientôt.
        </p>
    </section>

    <section class="max-w-6xl mx-auto px-4 pb-16 md:pb-24">
        <div class="grid grid-cols-1 md:grid-cols-[60%_40%] gap-12 md:gap-16 md:items-stretch">
            <div>
                <img
                    src="{{ Vite::asset('resources/images/before-after.jpg') }}"
                    alt="Avant / après : une photo transformée en tableau en bois Fragment"
                    class="w-full h-full object-cover rounded-lg"
                >
            </div>

            <div class="flex flex-col justify-center">
                <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
                    Le créateur
                </p>

                <h2 class="font-serif font-normal text-4xl leading-none text-foreground mt-4">
                    Un tableau unique, pensé par vous.
                </h2>

                <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
                    Au lieu de choisir un motif de la collection, vous partez de l'image de votre choix. Le créateur la traduit en pièces de bois, teinte par teinte, et vous montre l'aperçu de votre tableau avant de commander.
                </p>

                <p class="font-sans-soft text-foreground/80 mt-4 leading-relaxed">
                    Vous recevez ensuite le kit complet, prêt à assembler chez vous, comme pour n'importe quel Fragment.
                </p>
            </div>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 pb-16 md:pb-24">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-12 gap-y-12">
            <div class="border-t border-[#dbd3c6] pt-8">
                <span class="font-serif text-4xl text-primary">01</span>
                <h3 class="font-serif text-2xl text-foreground mt-4">Importez votre image</h3>
                <p class="font-sans-soft text-foreground/80 text-sm mt-2 leading-relaxed">Une photo de famille, un paysage, un dessin d'enfant : choisissez l'image que vous voulez voir prendre vie en bois.</p>
            </div>

            <div class="border-t border-[#dbd3c6] pt-8">
                <span class="font-serif text-4xl text-primary">02</span>
                <h3 class="font-serif text-2xl text-foreground mt-4">Ajustez votre tableau</h3>
                <p class="font-sans-soft text-foreground/80 text-sm mt-2 leading-relaxed">Choisissez le format et le cadrage, puis laissez le créateur associer chaque zone de l'image à une essence et une teinte de bois.</p>
            </div>

            <div class="border-t border-[#dbd3c6] pt-8">
                <span class="font-serif text-4xl text-primary">03</span>
                <h3 class="font-serif text-2xl text-foreground mt-4">Visualisez et commandez</h3>
                <p class="font-sans-soft text-foreground/80 text-sm mt-2 leading-relaxed">Découvrez l'aperçu de votre Fragment avant de le commander. Nous préparons ensuite votre kit à l'atelier.</p>
            </div>
        </div>
    </section>

    <section class="bg-[#f9f5ec] py-16 md:py-24">
        <div class="max-w-2xl mx-auto px-4 text-center">
            <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
                En attendant
            </p>

            <h2 class="font-serif font-normal text-5xl leading-none text-foreground mt-4">
                Le sur-mesure arrive bientôt.
            </h2>

            <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
                Nous peaufinons chaque détail du créateur à l'atelier. En attendant son lancement, découvrez les motifs de la collection, déjà prêts à être assemblés.
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
                    href="{{ route('shop.how-it-works') }}"
                    class="relative inline-flex items-center gap-1 font-sans-soft text-sm text-foreground after:content-[''] after:absolute after:left-0 after:-bottom-1 after:h-px after:w-full after:origin-left after:scale-x-0 after:bg-primary after:transition-transform after:duration-500 hover:after:scale-x-100"
                >
                    Comment ça marche
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14"/>
                        <path d="m13 6 6 6-6 6"/>
                    </svg>
                </a>
            </div>
        </div>
    </section>
@endsection
