<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fragment Boutique</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('builder/assets/index.css') }}">
    @stack('head')
</head>
<body class="fragment-theme antialiased" x-data="shop()">

    <header class="bg-card border-b border-border sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <nav class="flex items-center gap-6">
                <a href="{{ route('shop.index') }}" class="font-serif text-xl tracking-tight text-foreground">Fragment Boutique</a>
                <a href="{{ route('shop.products') }}" class="font-sans-soft text-sm transition {{ request()->routeIs('shop.products') ? 'text-foreground font-medium' : 'text-muted-foreground hover:text-foreground' }}">Produits</a>
                <a href="{{ route('shop.custom') }}" class="font-sans-soft text-sm transition {{ request()->routeIs('shop.custom') ? 'text-foreground font-medium' : 'text-muted-foreground hover:text-foreground' }}">Sur-mesure</a>
            </nav>
            <button @click="isCartOpen = !isCartOpen" class="relative flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm hover:opacity-90 transition">
                Panier
                <span x-show="totalItems > 0" class="bg-accent text-accent-foreground text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center" x-text="totalItems"></span>
            </button>
        </div>
    </header>

    <main class="@yield('main-class', 'max-w-6xl mx-auto px-4 py-10')">
        @yield('content')
    </main>

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
