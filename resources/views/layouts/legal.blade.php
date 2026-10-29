@extends('layouts.shop')

@section('main-class', 'w-full')

@section('content')
    <section class="max-w-2xl mx-auto px-4 py-16 md:py-16 text-center">
        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
            @yield('eyebrow', 'Informations')
        </p>

        <h2 class="font-serif font-normal text-5xl md:text-6xl leading-snug text-foreground mt-4">
            @yield('heading')
        </h2>

        <p class="font-sans-soft text-sm text-muted-foreground mt-6">
            Dernière mise à jour : @yield('updated-at')
        </p>
    </section>

    <article class="max-w-3xl mx-auto px-4 pb-16 md:pb-24 font-sans-soft text-foreground/80 leading-relaxed
        [&_h3]:font-serif [&_h3]:font-normal [&_h3]:text-2xl [&_h3]:text-foreground [&_h3]:mt-12 [&_h3]:mb-4 [&_h3]:pt-8 [&_h3]:border-t [&_h3]:border-[#dbd3c6]
        [&_h4]:font-sans-soft [&_h4]:font-medium [&_h4]:text-foreground [&_h4]:mt-6 [&_h4]:mb-2
        [&_p]:mb-4
        [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mb-4 [&_ul]:space-y-1
        [&_a]:text-foreground [&_a]:underline [&_a]:underline-offset-2 hover:[&_a]:text-primary
        [&_strong]:font-medium [&_strong]:text-foreground">
        @yield('legal-content')
    </article>
@endsection
