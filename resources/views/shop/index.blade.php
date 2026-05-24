@extends('layouts.shop')

@section('content')
    <div class="max-w-2xl">
        <h1 class="text-4xl font-semibold tracking-tight mb-4">Bienvenue chez<br>Fragment Boutique</h1>
        <p class="text-gray-500 text-lg mb-10 leading-relaxed">
            Découvrez notre catalogue de produits sélectionnés ou créez votre propre produit sur-mesure à prix libre.
        </p>
        <div class="flex flex-col sm:flex-row gap-4">
            <a href="{{ route('shop.products') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gray-900 text-white rounded-md text-sm font-medium hover:bg-gray-700 transition">
                Voir le catalogue →
            </a>
            <a href="{{ route('shop.custom') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-100 transition">
                Créer un produit sur-mesure
            </a>
        </div>
    </div>
@endsection
