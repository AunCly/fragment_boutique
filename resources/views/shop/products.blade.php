@extends('layouts.shop')

@section('content')
    <h2 class="font-serif text-3xl text-foreground mb-8">Catalogue</h2>

    @if(empty($products))
        <p class="font-sans-soft text-muted-foreground">Aucun produit disponible.</p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($products as $product)
                <div class="bg-card rounded-2xl border border-border overflow-hidden flex flex-col soft-shadow">
                    @if($product['image'])
                        <img src="{{ $product['image'] }}" alt="{{ $product['imageAlt'] }}" class="w-full h-48 object-cover">
                    @else
                        <div class="w-full h-48 bg-muted flex items-center justify-center text-muted-foreground font-sans-soft text-sm">Pas d'image</div>
                    @endif
                    <div class="p-5 flex flex-col flex-1">
                        <p class="font-sans-soft font-medium text-foreground flex-1">{{ $product['title'] }}</p>
                        <p class="font-sans-soft text-muted-foreground text-sm mt-1">{{ number_format((float) $product['price'], 2, ',', ' ') }} {{ $product['currency'] }}</p>
                        <button
                            class="mt-4 w-full py-2.5 px-4 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm hover:opacity-90 transition"
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
