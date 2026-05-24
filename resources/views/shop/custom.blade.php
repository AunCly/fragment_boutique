@extends('layouts.shop')

@section('content')
    <div class="max-w-xl">
        <h2 class="text-2xl font-semibold mb-2">Produit sur-mesure</h2>
        <p class="text-sm text-gray-500 mb-8">Définissez un nom et un prix libre pour votre produit personnalisé.</p>

        <div class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom du produit</label>
                <input
                    type="text"
                    x-model="customTitle"
                    placeholder="Ex : Création graphique personnalisée"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:border-gray-500"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Prix (€)</label>
                <input
                    type="number"
                    x-model="customPrice"
                    placeholder="0.00"
                    min="0"
                    step="0.01"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:border-gray-500"
                >
            </div>
            <button
                @click="addCustom()"
                :disabled="!customTitle || !customPrice"
                :class="(!customTitle || !customPrice) ? 'opacity-40 cursor-not-allowed' : ''"
                class="w-full px-4 py-2 bg-gray-900 text-white rounded-md text-sm hover:bg-gray-700 transition"
            >
                Ajouter au panier
            </button>
        </div>
    </div>
@endsection
