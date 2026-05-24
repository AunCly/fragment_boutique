@extends('layouts.shop')

@section('content')
    <h2 class="text-2xl font-semibold mb-8">Catalogue</h2>

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
@endsection
