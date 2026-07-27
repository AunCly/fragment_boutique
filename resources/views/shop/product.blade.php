@extends('layouts.shop')

@section('main-class', 'w-full')

@section('content')
    <section class="max-w-6xl mx-auto px-4 py-8">
        <a
            href="{{ route('shop.products') }}"
            class="inline-flex items-center gap-2 font-sans-soft text-sm text-foreground hover:text-primary transition"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 12H5"/>
                <path d="m11 18-6-6 6-6"/>
            </svg>
            Retour à la collection
        </a>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 mt-8" x-data="productPage(@js($product))">
            <div>
                <div class="w-full aspect-square rounded-lg overflow-hidden bg-muted" x-show="mainImage">
                    <img :src="mainImage" :alt="product.title" class="w-full h-full object-cover">
                </div>
                <div class="w-full aspect-square bg-muted rounded-lg flex items-center justify-center text-muted-foreground font-sans-soft text-sm" x-show="!mainImage">Pas d'image</div>

                <div class="grid grid-cols-3 gap-3 mt-3" x-show="product.images.length > 1">
                    <template x-for="image in product.images" :key="image.url">
                        <button
                            type="button"
                            @click="selectImage(image.url)"
                            class="aspect-square rounded-lg overflow-hidden border-2 transition"
                            :class="mainImage === image.url ? 'border-primary' : 'border-transparent'"
                        >
                            <img :src="image.url" :alt="image.alt" class="w-full h-full object-cover">
                        </button>
                    </template>
                </div>
            </div>

            <div>
                <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground" x-text="product.category"></p>

                <h1 class="font-serif font-normal text-4xl leading-tight text-foreground mt-3" x-text="product.title"></h1>

                <p class="font-serif text-2xl text-primary mt-4" x-text="currentPriceFormatted"></p>

                <div
                    class="font-sans-soft text-sm text-foreground/80 leading-relaxed mt-6 [&_p]:mt-3 [&_p:first-child]:mt-0 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mt-3"
                    x-show="product.description"
                    x-html="product.description"
                ></div>

                <template x-for="option in visibleOptions" :key="option.name">
                    <div class="mt-8">
                        <span class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground" x-text="option.name"></span>
                        <div class="flex gap-2 mt-3" :class="option.name === 'Finition' ? '' : 'flex-wrap'">
                            <template x-for="item in optionValues(option)" :key="item.value">
                                <button
                                    type="button"
                                    :disabled="!item.available"
                                    @click="selectOption(option.name, item.value)"
                                    :class="[
                                        !item.available
                                            ? 'border-border text-muted-foreground line-through opacity-40 cursor-not-allowed'
                                            : (selected[option.name] === item.value ? 'bg-primary text-white border-primary' : 'border-border text-foreground hover:border-primary'),
                                        option.name === 'Finition' ? 'flex-1 items-start text-left' : 'items-center text-center',
                                    ]"
                                    class="flex flex-col gap-1 px-7 py-4 border font-sans-soft text-xs uppercase tracking-wide transition"
                                >
                                    <span class="flex items-center gap-1.5">
                                        <svg
                                            x-show="option.name === 'Finition' && selected[option.name] === item.value"
                                            xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                                        >
                                            <path d="M20 6 9 17l-5-5"/>
                                        </svg>
                                        <span x-text="item.dimension ? `${item.value} · ${item.dimension}` : item.value"></span>
                                    </span>
                                    <span
                                        x-show="item.value === 'Avec cadre'"
                                        class="text-[10px] tracking-widest"
                                        :class="selected[option.name] === item.value && item.available ? 'text-white/80' : 'text-primary'"
                                    >Recommandé</span>
                                    <span
                                        x-show="item.value === 'Sans cadre'"
                                        class="text-[10px] tracking-widest"
                                        :class="selected[option.name] === item.value && item.available ? 'text-white/80' : 'text-primary'"
                                    >-20 €</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <button
                    type="button"
                    @click="addVariant({ variantId: currentVariant.id, title: product.title, price: parseFloat(currentVariant.price), currency: currentVariant.currency, image: mainImage, options: { ...selected } })"
                    class="w-full inline-flex items-center justify-center gap-2 mt-10 px-8 py-4 bg-primary text-background font-sans-soft text-sm uppercase hover:opacity-90 transition"
                >
                    <span x-text="'Ajouter au panier — ' + currentPriceFormatted"></span>
                </button>

                <p class="flex items-center justify-center gap-2 mt-4 font-sans-soft text-xs uppercase tracking-wide text-muted-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .962 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.582a.5.5 0 0 1 0 .962L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.962 0z"/>
                    </svg>
                    Aucun outil requis · Plan de montage inclus
                </p>

                <div class="border-t border-border mt-8"></div>

                <div class="mt-8">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="text-foreground shrink-0">
                            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.73Z"/>
                            <path d="m3.3 7 8.7 5 8.7-5"/>
                            <path d="M12 22V12"/>
                        </svg>
                        <p class="font-serif text-lg text-foreground">Ce que vous recevez</p>
                    </div>
                    <ul class="mt-4 space-y-2">
                        <li class="font-sans-soft text-sm text-foreground/80">— Cadre en chêne massif</li>
                        <li class="font-sans-soft text-sm text-foreground/80">— Environ 1100 pièces de bois triées par teinte</li>
                        <li class="font-sans-soft text-sm text-foreground/80">— Pince en bois</li>
                        <li class="font-sans-soft text-sm text-foreground/80">— Plan de montage illustré</li>
                        <li class="font-sans-soft text-sm text-foreground/80">— Notice d'assemblage</li>
                    </ul>
                </div>

                <div class="border-t border-border mt-8"></div>

                <div class="mt-8">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="text-foreground shrink-0">
                            <path d="M10 10v.2A3 3 0 0 1 8.9 16H5a3 3 0 0 1-1-5.8V10a3 3 0 0 1 6 0Z"/>
                            <path d="M7 16v6"/>
                            <path d="M13 19v3"/>
                            <path d="M12 19h8.3a1 1 0 0 0 .7-1.7L18 14h.3a1 1 0 0 0 .7-1.7L16 9h.2a1 1 0 0 0 .8-1.7L13 3l-1.4 1.5"/>
                        </svg>
                        <p class="font-serif text-lg text-foreground">Bois utilisés</p>
                    </div>
                    <p class="font-sans-soft text-sm text-foreground/80 mt-3">Chêne français, hêtre, noyer et frêne teinté vert.</p>
                </div>

                <div class="border-t border-border mt-8"></div>

                <p class="font-sans-soft text-sm text-foreground/80 mt-8">Comptez une à trois soirées pour l'assemblage. Vous n'avez besoin que de la pince fournie et du plan de montage — aucun outil, aucune compétence particulière.</p>
            </div>
        </div>
    </section>
@endsection
