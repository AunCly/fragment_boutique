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

    {{-- Header --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold">Fragment Boutique</h1>
            <button @click="isCartOpen = !isCartOpen" class="relative flex items-center gap-2 px-4 py-2 bg-gray-900 text-white rounded-md text-sm hover:bg-gray-700 transition">
                Panier
                <span x-show="totalItems > 0" class="bg-white text-gray-900 text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center" x-text="totalItems"></span>
            </button>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-10 space-y-12">

        {{-- Produits Shopify --}}
        <section>
            <h2 class="text-lg font-semibold mb-6">Catalogue</h2>
            @if(empty($products))
                <p class="text-gray-500">Aucun produit disponible.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($products as $product)
                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden flex flex-col">
                            @if($product['image'])
                                <img src="{{ $product['image'] }}" alt="{{ $product['imageAlt'] }}" class="w-full h-48 object-cover">
                            @else
                                <div class="w-full h-48 bg-gray-100 flex items-center justify-center text-gray-400 text-sm">Pas d'image</div>
                            @endif
                            <div class="p-4 flex flex-col flex-1">
                                <p class="font-medium flex-1">{{ $product['title'] }}</p>
                                <p class="text-gray-600 text-sm mt-1">{{ number_format((float) $product['price'], 2, ',', ' ') }} {{ $product['currency'] }}</p>
                                <button
                                    class="mt-4 w-full py-2 px-4 bg-gray-900 text-white rounded-md text-sm hover:bg-gray-700 transition"
                                    @click="addVariant({
                                        variantId: '{{ $product['variantId'] }}',
                                        title: '{{ addslashes($product['title']) }}',
                                        price: {{ $product['price'] }},
                                        currency: '{{ $product['currency'] }}'
                                    })"
                                >
                                    Ajouter au panier
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Builder simulé --}}
        <section class="bg-white border border-gray-200 rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Produit sur-mesure</h2>
            <p class="text-sm text-gray-500 mb-6">Simule un produit à prix libre (builder)</p>
            <div class="flex flex-col sm:flex-row gap-4">
                <input
                    type="text"
                    x-model="customTitle"
                    placeholder="Nom du produit"
                    class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:border-gray-500"
                >
                <input
                    type="number"
                    x-model="customPrice"
                    placeholder="Prix (€)"
                    min="0"
                    step="0.01"
                    class="w-36 border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:border-gray-500"
                >
                <button
                    @click="addCustom()"
                    class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm hover:bg-gray-700 transition"
                    :disabled="!customTitle || !customPrice"
                    :class="(!customTitle || !customPrice) ? 'opacity-40 cursor-not-allowed' : ''"
                >
                    Ajouter au panier
                </button>
            </div>
        </section>

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
