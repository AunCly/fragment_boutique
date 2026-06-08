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

    $statusLabel = $fulfillmentLabels[$order['fulfillmentStatus']] ?? $order['fulfillmentStatus'];
    $statusColor = $fulfillmentColors[$order['fulfillmentStatus']] ?? 'text-muted-foreground bg-muted border-border';
    $date = \Carbon\Carbon::parse($order['processedAt'])->locale('fr')->isoFormat('D MMMM YYYY');
@endphp

@section('content')
<div class="max-w-3xl mx-auto py-12 px-4">

    {{-- Back --}}
    <a href="{{ route('account.dashboard') }}" class="inline-flex items-center gap-1.5 font-sans-soft text-sm text-muted-foreground hover:text-foreground transition mb-8">
        ← Mes commandes
    </a>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="font-serif text-3xl text-foreground">Commande #{{ $order['orderNumber'] }}</h1>
            <p class="font-sans-soft text-sm text-muted-foreground mt-1">{{ $date }}</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="font-sans-soft text-xs px-3 py-1 rounded-full border {{ $statusColor }}">{{ $statusLabel }}</span>
            <a
                href="{{ route('account.orders.invoice', $order['orderNumber']) }}"
                class="inline-flex items-center gap-1.5 font-sans-soft text-sm px-4 py-2 border border-border rounded-full hover:bg-muted transition"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                Facture PDF
            </a>
        </div>
    </div>

    {{-- Articles --}}
    <div class="border border-border rounded-2xl bg-card overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-border">
            <h2 class="font-serif text-base text-foreground">Articles</h2>
        </div>
        <div class="divide-y divide-border">
            @foreach ($order['lineItems'] as $item)
                <div class="flex items-center gap-4 px-5 py-4">
                    @if ($item['image'])
                        <img src="{{ $item['image']['url'] }}" alt="{{ $item['image']['altText'] ?? $item['title'] }}" class="w-12 h-12 object-cover rounded-lg bg-muted shrink-0">
                    @else
                        <div class="w-12 h-12 rounded-lg bg-muted shrink-0"></div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <p class="font-sans-soft text-sm text-foreground truncate">{{ $item['title'] }}</p>
                        <p class="font-sans-soft text-xs text-muted-foreground mt-0.5">Qté : {{ $item['quantity'] }}</p>
                    </div>
                    @if ($item['price'])
                        <p class="font-sans-soft text-sm text-foreground shrink-0">
                            {{ number_format((float) $item['price']['amount'] * $item['quantity'], 2, ',', ' ') }} €
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-6 mb-6">

        {{-- Suivi --}}
        @if (!empty($order['trackingInfo']))
            <div class="border border-border rounded-2xl bg-card p-5">
                <h2 class="font-serif text-base text-foreground mb-4">Suivi de livraison</h2>
                <div class="space-y-3">
                    @foreach ($order['trackingInfo'] as $tracking)
                        <div>
                            <p class="font-sans-soft text-xs text-muted-foreground mb-1">Numéro de suivi</p>
                            @if ($tracking['url'])
                                <a
                                    href="{{ $tracking['url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-sans-soft text-sm text-foreground underline underline-offset-2 hover:opacity-70 transition break-all"
                                >{{ $tracking['number'] }}</a>
                            @else
                                <p class="font-sans-soft text-sm text-foreground">{{ $tracking['number'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Adresse de livraison --}}
        @if ($order['shippingAddress'])
            @php $addr = $order['shippingAddress']; @endphp
            <div class="border border-border rounded-2xl bg-card p-5">
                <h2 class="font-serif text-base text-foreground mb-4">Adresse de livraison</h2>
                <address class="not-italic font-sans-soft text-sm text-muted-foreground space-y-0.5">
                    <p class="text-foreground font-medium">{{ trim($addr['firstName'] . ' ' . $addr['lastName']) }}</p>
                    <p>{{ $addr['address1'] }}</p>
                    @if ($addr['address2'])
                        <p>{{ $addr['address2'] }}</p>
                    @endif
                    <p>{{ $addr['zip'] }} {{ $addr['city'] }}</p>
                    @if ($addr['province'])
                        <p>{{ $addr['province'] }}</p>
                    @endif
                    <p>{{ $addr['country'] }}</p>
                </address>
            </div>
        @endif
    </div>

    {{-- Récapitulatif --}}
    <div class="border border-border rounded-2xl bg-card p-5">
        <h2 class="font-serif text-base text-foreground mb-4">Récapitulatif</h2>
        <div class="space-y-2">
            <div class="flex justify-between font-sans-soft text-sm">
                <span class="text-muted-foreground">Sous-total</span>
                <span class="text-foreground">{{ number_format((float) $order['subtotalPrice']['amount'], 2, ',', ' ') }} €</span>
            </div>
            <div class="flex justify-between font-sans-soft text-sm">
                <span class="text-muted-foreground">Livraison</span>
                <span class="text-foreground">
                    @if ((float) $order['totalShippingPrice']['amount'] === 0.0)
                        Offerte
                    @else
                        {{ number_format((float) $order['totalShippingPrice']['amount'], 2, ',', ' ') }} €
                    @endif
                </span>
            </div>
            @if ((float) $order['totalTax']['amount'] > 0)
                <div class="flex justify-between font-sans-soft text-sm">
                    <span class="text-muted-foreground">TVA</span>
                    <span class="text-foreground">{{ number_format((float) $order['totalTax']['amount'], 2, ',', ' ') }} €</span>
                </div>
            @endif
            <div class="flex justify-between pt-3 border-t border-border">
                <span class="font-serif text-foreground">Total</span>
                <span class="font-serif text-lg text-foreground">{{ number_format((float) $order['totalPrice']['amount'], 2, ',', ' ') }} €</span>
            </div>
        </div>
    </div>

</div>
@endsection
