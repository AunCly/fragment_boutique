<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fragment Boutique</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ Vite::fonts() }}
    @stack('head')
</head>
<body class="antialiased bg-background text-foreground" x-data="shop()">

    <header class="bg-background border-b border-[#dbd3c6] sticky top-0 z-40">
        <div class="relative max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('shop.index') }}" class="font-serif text-2xl tracking-tight text-foreground">Fragment<span class="text-primary">.</span></a>

            <nav class="hidden md:flex items-center gap-8 absolute left-1/2 -translate-x-1/2">
                <a href="{{ route('shop.products') }}" class="relative font-sans-soft text-sm text-foreground after:content-[''] after:absolute after:left-0 after:-bottom-1 after:h-px after:w-full after:origin-left after:scale-x-0 after:bg-primary after:transition-transform after:duration-500 hover:after:scale-x-100">Collection</a>
                <span class="relative font-sans-soft text-sm text-foreground after:content-[''] after:absolute after:left-0 after:-bottom-1 after:h-px after:w-full after:origin-left after:scale-x-0 after:bg-primary after:transition-transform after:duration-500 hover:after:scale-x-100">Créez votre fragment</span>
                <a href="{{ route('shop.how-it-works') }}" class="relative font-sans-soft text-sm text-foreground after:content-[''] after:absolute after:left-0 after:-bottom-1 after:h-px after:w-full after:origin-left after:scale-x-0 after:bg-primary after:transition-transform after:duration-500 hover:after:scale-x-100">Comment ça marche</a>
                <a href="{{ route('shop.workshop') }}" class="relative font-sans-soft text-sm text-foreground after:content-[''] after:absolute after:left-0 after:-bottom-1 after:h-px after:w-full after:origin-left after:scale-x-0 after:bg-primary after:transition-transform after:duration-500 hover:after:scale-x-100">Atelier</a>
                <span class="relative font-sans-soft text-sm text-foreground after:content-[''] after:absolute after:left-0 after:-bottom-1 after:h-px after:w-full after:origin-left after:scale-x-0 after:bg-primary after:transition-transform after:duration-500 hover:after:scale-x-100">Galerie</span>
            </nav>

            <div class="flex items-center gap-1">
                @if(session('shopify_customer_token'))
                    <a href="{{ route('account.dashboard') }}" aria-label="Mon compte" class="flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-muted hover:text-accent {{ request()->routeIs('account.*') ? 'text-accent' : 'text-foreground' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21a8 8 0 0 0-16 0"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </a>
                @else
                    <a href="{{ route('account.login') }}" aria-label="Connexion" class="flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-muted hover:text-accent {{ request()->routeIs('account.*') ? 'text-accent' : 'text-foreground' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21a8 8 0 0 0-16 0"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </a>
                @endif
                <button @click="isCartOpen = !isCartOpen" aria-label="Panier" class="relative flex h-10 w-10 items-center justify-center rounded-full text-foreground transition hover:bg-muted hover:text-accent">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/>
                        <path d="M3 6h18"/>
                        <path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                    <span x-show="totalItems > 0" class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-accent text-[10px] font-bold text-accent-foreground" x-text="totalItems"></span>
                </button>
            </div>
        </div>
    </header>

    <main class="@yield('main-class', 'max-w-6xl mx-auto px-4 py-10')">
        @yield('content')
    </main>

    <footer class="bg-[#f9f5ec] border-t border-[#dbd3c6]">
        <div class="max-w-6xl mx-auto px-4 pt-16 pb-6">
            <div class="grid grid-cols-1 md:grid-cols-[50%_50%] gap-12">
                <div>
                    <h2 class="font-serif font-normal text-3xl leading-snug text-foreground">
                        Fragment est un atelier français
                        <br>
                        qui fabrique des tableaux en bois à assembler soi-même.
                    </h2>

                    <p class="font-sans-soft text-sm text-foreground/80 mt-6 leading-relaxed">
                        Vous choisissez un motif, vous recevez toutes les pièces, vous l'assemblez chez vous. Chaque kit est dessiné, découpé et préparé dans notre atelier.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">Boutique</p>
                        <ul class="mt-4 space-y-1.5">
                            <li><a href="#" class="font-sans-soft text-sm text-foreground/80 hover:text-foreground transition">Collection</a></li>
                            <li><a href="#" class="font-sans-soft text-sm text-foreground/80 hover:text-foreground transition">Comment ça marche</a></li>
                            <li><a href="#" class="font-sans-soft text-sm text-foreground/80 hover:text-foreground transition">L'atelier</a></li>
                            <li><a href="#" class="font-sans-soft text-sm text-foreground/80 hover:text-foreground transition">Galerie</a></li>
                        </ul>
                    </div>

                    <div>
                        <p class="font-sans-soft text-xs uppercase tracking-widest text-muted-foreground">Maison</p>
                        <ul class="mt-4 space-y-1.5">
                            <li><a href="#" class="font-sans-soft text-sm text-foreground/80 hover:text-foreground transition">Contact</a></li>
                            <li><a href="#" class="font-sans-soft text-sm text-foreground/80 hover:text-foreground transition">Livraison</a></li>
                            <li><a href="#" class="font-sans-soft text-sm text-foreground/80 hover:text-foreground transition">Retours</a></li>
                            <li><a href="#" class="font-sans-soft text-sm text-foreground/80 hover:text-foreground transition">Instagram</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="border-t border-[#dbd3c6] mt-12 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="font-sans-soft text-xs text-muted-foreground">© {{ date('Y') }} Fragment — Fabriqué en France</p>

                <div class="flex items-center gap-2">
                    <a href="#" class="font-sans-soft text-xs text-muted-foreground hover:text-foreground transition">Mentions légales</a>
                    <span class="font-sans-soft text-xs text-muted-foreground">·</span>
                    <a href="#" class="font-sans-soft text-xs text-muted-foreground hover:text-foreground transition">CGV</a>
                    <span class="font-sans-soft text-xs text-muted-foreground">·</span>
                    <a href="#" class="font-sans-soft text-xs text-muted-foreground hover:text-foreground transition">Confidentialité</a>
                </div>
            </div>
        </div>
    </footer>

    {{-- Panier flottant --}}
    <div
        x-show="isCartOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-x-4"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 translate-x-4"
        class="fixed top-0 right-0 h-full w-80 bg-card border-l border-border shadow-xl z-50 flex flex-col"
    >
        <div class="flex items-center justify-between px-5 py-4 border-b border-border">
            <h2 class="font-serif text-lg text-foreground">Panier</h2>
            <button @click="isCartOpen = false" class="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
            <template x-if="items.length === 0">
                <p class="font-sans-soft text-muted-foreground text-sm text-center mt-8">Votre panier est vide</p>
            </template>
            <template x-for="(item, index) in items" :key="index">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1">
                        <p class="font-sans-soft text-sm font-medium text-foreground" x-text="item.title"></p>
                        <p class="font-sans-soft text-xs text-muted-foreground mt-0.5" x-text="formatPrice(item.price, item.currency)"></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="decreaseQty(index)" class="w-6 h-6 rounded-full border border-border text-sm flex items-center justify-center hover:bg-muted text-foreground">−</button>
                        <span class="font-sans-soft text-sm w-4 text-center text-foreground" x-text="item.quantity"></span>
                        <button @click="increaseQty(index)" class="w-6 h-6 rounded-full border border-border text-sm flex items-center justify-center hover:bg-muted text-foreground">+</button>
                        <button @click="removeItem(index)" class="text-muted-foreground hover:text-destructive ml-1 text-sm">✕</button>
                    </div>
                </div>
            </template>
        </div>

        <div class="border-t border-border px-5 py-4 space-y-4">
            <div class="flex justify-between text-sm font-semibold">
                <span class="font-sans-soft text-muted-foreground">Total</span>
                <span class="font-serif text-lg text-foreground" x-text="formatPrice(totalPrice, 'EUR')"></span>
            </div>
            <button
                @click="checkout()"
                :disabled="items.length === 0"
                :class="items.length === 0 ? 'opacity-40 cursor-not-allowed' : ''"
                class="w-full py-2.5 px-4 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm hover:opacity-90 transition"
            >
                <span x-show="!isCheckingOut">Passer commande →</span>
                <span x-show="isCheckingOut">Chargement...</span>
            </button>
        </div>
    </div>

    {{-- Overlay --}}
    <div x-show="isCartOpen" @click="isCartOpen = false" class="fixed inset-0 bg-foreground/20 z-40"></div>

    @stack('body')
</body>
</html>
