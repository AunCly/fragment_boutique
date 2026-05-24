<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fragment Boutique</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased" x-data="shop()">

    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <nav class="flex items-center gap-6">
                <a href="{{ route('shop.index') }}" class="text-xl font-semibold tracking-tight">Fragment Boutique</a>
                <a href="{{ route('shop.products') }}" class="text-sm transition {{ request()->routeIs('shop.products') ? 'text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-900' }}">Produits</a>
                <a href="{{ route('shop.custom') }}" class="text-sm transition {{ request()->routeIs('shop.custom') ? 'text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-900' }}">Sur-mesure</a>
            </nav>
            <button @click="isCartOpen = !isCartOpen" class="relative flex items-center gap-2 px-4 py-2 bg-gray-900 text-white rounded-md text-sm hover:bg-gray-700 transition">
                Panier
                <span x-show="totalItems > 0" class="bg-white text-gray-900 text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center" x-text="totalItems"></span>
            </button>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-10">
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
        class="fixed top-0 right-0 h-full w-80 bg-white border-l border-gray-200 shadow-xl z-50 flex flex-col"
    >
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h2 class="font-semibold">Panier</h2>
            <button @click="isCartOpen = false" class="text-gray-400 hover:text-gray-700 text-xl leading-none">&times;</button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
            <template x-if="items.length === 0">
                <p class="text-gray-400 text-sm text-center mt-8">Votre panier est vide</p>
            </template>
            <template x-for="(item, index) in items" :key="index">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1">
                        <p class="text-sm font-medium" x-text="item.title"></p>
                        <p class="text-xs text-gray-500 mt-0.5" x-text="formatPrice(item.price, item.currency)"></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="decreaseQty(index)" class="w-6 h-6 rounded border border-gray-300 text-sm flex items-center justify-center hover:bg-gray-100">−</button>
                        <span class="text-sm w-4 text-center" x-text="item.quantity"></span>
                        <button @click="increaseQty(index)" class="w-6 h-6 rounded border border-gray-300 text-sm flex items-center justify-center hover:bg-gray-100">+</button>
                        <button @click="removeItem(index)" class="text-gray-400 hover:text-red-500 ml-1 text-sm">✕</button>
                    </div>
                </div>
            </template>
        </div>

        <div class="border-t border-gray-200 px-5 py-4 space-y-4">
            <div class="flex justify-between text-sm font-semibold">
                <span>Total</span>
                <span x-text="formatPrice(totalPrice, 'EUR')"></span>
            </div>
            <button
                @click="checkout()"
                :disabled="items.length === 0"
                :class="items.length === 0 ? 'opacity-40 cursor-not-allowed' : ''"
                class="w-full py-2 px-4 bg-gray-900 text-white rounded-md text-sm hover:bg-gray-700 transition"
            >
                <span x-show="!isCheckingOut">Passer commande →</span>
                <span x-show="isCheckingOut">Chargement...</span>
            </button>
        </div>
    </div>

    {{-- Overlay --}}
    <div x-show="isCartOpen" @click="isCartOpen = false" class="fixed inset-0 bg-black/20 z-40"></div>

</body>
</html>
