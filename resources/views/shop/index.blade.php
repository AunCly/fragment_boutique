@extends('layouts.shop')

@section('main-class', 'w-full')

@section('content')
    <section class="relative flex min-h-[88vh] items-center justify-center overflow-hidden px-5 sm:px-8 py-24 sm:py-32">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 opacity-[0.07]"
            style="background-image: radial-gradient(circle at 20% 30%, hsl(28 40% 35%) 0, transparent 50%), radial-gradient(circle at 80% 70%, hsl(25 25% 25%) 0, transparent 55%);"
        ></div>
        <div class="relative mx-auto max-w-3xl text-center fade-in-up">
            <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-border bg-card/60 px-4 py-1.5 backdrop-blur">
                <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                <span class="font-sans-soft text-[11px] uppercase tracking-[0.22em] text-muted-foreground">
                    Composition artisanale en bois
                </span>
            </div>
            <h1 class="font-serif text-5xl sm:text-7xl leading-[0.95] tracking-tight text-foreground">
                Créez votre <span class="italic text-accent">Fragment</span>
            </h1>
            <p class="mt-6 font-sans-soft text-lg sm:text-xl leading-relaxed text-muted-foreground">
                Transformez une image en composition de bois artisanale.
            </p>
            <div class="mt-12 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4">
                <a
                    href="{{ route('shop.custom') }}"
                    class="group inline-flex items-center justify-center gap-2 rounded-full bg-primary px-8 py-4 font-sans-soft text-base font-medium text-primary-foreground transition-all duration-500 hover:opacity-95 soft-shadow-lg"
                >
                    Commencer
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-500 group-hover:translate-y-0.5" aria-hidden="true">
                        <path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>
                    </svg>
                </a>
                <a
                    href="{{ route('shop.products') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-border bg-card/60 px-8 py-4 font-sans-soft text-base text-foreground hover:bg-muted transition-all duration-300"
                >
                    Voir le catalogue
                </a>
            </div>
        </div>
    </section>
@endsection
