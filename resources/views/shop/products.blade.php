@extends('layouts.shop')

@section('main-class', 'w-full')

@section('content')
    <section class="max-w-2xl mx-auto px-4 py-16 md:py-24 text-center">
        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">
            Collection
        </p>

        <h2 class="font-serif font-normal text-5xl md:text-5xl leading-snug text-foreground mt-8">
            Choisissez le tableau que vous allez assembler.
        </h2>

        <p class="font-sans-soft text-foreground/80 mt-6 leading-relaxed">
            Chaque tableau est livré en kit : le cadre, toutes les pièces de bois, la pince et le plan de montage. Vous l'assemblez chez vous.
        </p>
    </section>

    <section
        class="max-w-6xl mx-auto px-4 pb-16 md:pb-24"
        x-data="collectionFilters(@js($products), @js($filterGroups))"
    >
        @if(count($filterGroups))
            <div class="flex flex-col gap-4 mb-12">
                <template x-for="group in groups" :key="group.name">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground shrink-0" x-text="group.name"></span>
                        <button
                            type="button"
                            @click="toggle(group.name, null)"
                            :class="!selected[group.name] ? 'bg-primary text-white border-primary' : 'border-border text-foreground hover:border-primary'"
                            class="px-5 py-2.5 border font-sans-soft text-xs uppercase tracking-wide transition"
                        >TOUT</button>
                        <template x-for="value in group.values" :key="value">
                            <button
                                type="button"
                                @click="toggle(group.name, value)"
                                :class="selected[group.name] === value ? 'bg-primary text-white border-primary' : 'border-border text-foreground hover:border-primary'"
                                class="px-5 py-2.5 border font-sans-soft text-xs uppercase tracking-wide transition"
                                x-text="value"
                            ></button>
                        </template>
                    </div>
                </template>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <template x-for="product in filtered" :key="product.id">
                <div class="group">
                    <div class="w-full aspect-square rounded-lg overflow-hidden" x-show="product.image">
                        <img :src="product.image" :alt="product.imageAlt" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                    </div>
                    <div class="w-full aspect-square bg-muted rounded-lg flex items-center justify-center text-muted-foreground font-sans-soft text-sm" x-show="!product.image">Pas d'image</div>
                    <div class="flex items-start justify-between mt-4">
                        <div>
                            <p class="font-serif text-foreground" x-text="product.title"></p>
                            <p class="font-sans-soft uppercase text-xs text-muted-foreground mt-1" x-text="Object.values(product.tags).join(' · ')"></p>
                        </div>
                        <p class="font-sans-soft text-sm text-foreground/80" x-text="product.priceFormatted"></p>
                    </div>
                </div>
            </template>
        </div>
    </section>
@endsection
