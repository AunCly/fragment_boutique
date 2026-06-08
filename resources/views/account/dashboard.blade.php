@extends('layouts.shop')

@php
    $fulfillmentLabels = [
        'FULFILLED' => 'Livré',
        'UNFULFILLED' => 'En préparation',
        'PARTIALLY_FULFILLED' => 'Partiellement expédié',
        'IN_TRANSIT' => 'En transit',
        'OUT_FOR_DELIVERY' => 'En livraison',
        'DELIVERED' => 'Livré',
        'ATTEMPTED_DELIVERY' => 'Tentative de livraison',
        'RESTOCKED' => 'Retourné en stock',
        'PENDING_FULFILLMENT' => 'En attente',
        'ON_HOLD' => 'En attente',
        'OPEN' => 'En cours',
        'IN_PROGRESS' => 'En cours',
        'SCHEDULED' => 'Planifié',
    ];

    $fulfillmentColors = [
        'FULFILLED' => 'text-green-700 bg-green-50 border-green-200',
        'DELIVERED' => 'text-green-700 bg-green-50 border-green-200',
        'IN_TRANSIT' => 'text-blue-700 bg-blue-50 border-blue-200',
        'OUT_FOR_DELIVERY' => 'text-blue-700 bg-blue-50 border-blue-200',
        'PARTIALLY_FULFILLED' => 'text-amber-700 bg-amber-50 border-amber-200',
        'ATTEMPTED_DELIVERY' => 'text-red-700 bg-red-50 border-red-200',
    ];
@endphp

@section('content')
<div class="max-w-3xl mx-auto py-12 px-4">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-10">
        <div>
            <h1 class="font-serif text-3xl text-foreground">Bonjour, {{ $customer['firstName'] }}</h1>
            <p class="font-sans-soft text-sm text-muted-foreground mt-1">{{ $customer['email'] }}</p>
        </div>
        <div class="flex items-center gap-4">
            <a href="{{ route('account.profile') }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">Mon profil</a>
            <form action="{{ route('account.logout') }}" method="POST">
                @csrf
                <button type="submit" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
                    Déconnexion
                </button>
            </form>
        </div>
    </div>

    {{-- Orders --}}
    <h2 class="font-serif text-lg text-foreground mb-5">Mes commandes</h2>

    @if (empty($customer['orders']))
        <div class="text-center py-16 border border-border rounded-2xl bg-muted/30">
            <p class="font-sans-soft text-muted-foreground">Vous n'avez pas encore passé de commande.</p>
            <a href="{{ route('shop.products') }}" class="mt-4 inline-block font-sans-soft text-sm text-foreground underline underline-offset-2 hover:opacity-70 transition">
                Découvrir nos produits
            </a>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($customer['orders'] as $order)
                @php
                    $statusLabel = $fulfillmentLabels[$order['fulfillmentStatus']] ?? $order['fulfillmentStatus'];
                    $statusColor = $fulfillmentColors[$order['fulfillmentStatus']] ?? 'text-muted-foreground bg-muted border-border';
                    $amount = number_format((float) $order['totalPrice']['amount'], 2, ',', ' ') . ' €';
                    $date = \Carbon\Carbon::parse($order['processedAt'])->locale('fr')->isoFormat('D MMMM YYYY');
                @endphp

                <div class="border border-border rounded-2xl bg-card p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-1">
                            <span class="font-serif text-foreground">Commande #{{ $order['orderNumber'] }}</span>
                            <span class="font-sans-soft text-xs px-2 py-0.5 rounded-full border {{ $statusColor }}">
                                {{ $statusLabel }}
                            </span>
                        </div>
                        <p class="font-sans-soft text-xs text-muted-foreground">{{ $date }}</p>
                    </div>

                    <div class="flex items-center gap-4">
                        <span class="font-serif text-lg text-foreground">{{ $amount }}</span>
                        <a
                            href="{{ route('account.orders.show', $order['orderNumber']) }}"
                            class="font-sans-soft text-sm px-4 py-2 border border-border rounded-full hover:bg-muted transition"
                        >
                            Détail →
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
