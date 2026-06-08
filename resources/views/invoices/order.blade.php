<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            line-height: 1.5;
            padding: 48px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e5e5e5;
        }

        .brand {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: -0.5px;
        }

        .brand-sub {
            font-size: 10px;
            color: #888;
            margin-top: 2px;
        }

        .invoice-meta {
            text-align: right;
        }

        .invoice-meta .label {
            font-size: 10px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .invoice-meta .value {
            font-size: 14px;
            font-weight: bold;
            margin-top: 2px;
        }

        .invoice-meta .date {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
        }

        .addresses {
            display: flex;
            gap: 40px;
            margin-bottom: 36px;
        }

        .address-block {
            flex: 1;
        }

        .address-block .section-label {
            font-size: 9px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
        }

        .address-block p {
            margin-bottom: 2px;
            color: #333;
        }

        .address-block .name {
            font-weight: bold;
            color: #1a1a1a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        thead th {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #888;
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid #e5e5e5;
        }

        thead th:last-child {
            text-align: right;
        }

        tbody td {
            padding: 10px 10px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
        }

        tbody td:last-child {
            text-align: right;
            font-weight: 500;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .totals {
            width: 240px;
            margin-left: auto;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 11px;
        }

        .totals-row .totals-label {
            color: #888;
        }

        .totals-divider {
            border-top: 1px solid #e5e5e5;
            margin: 6px 0;
        }

        .totals-total {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-weight: bold;
            font-size: 13px;
        }

        .footer {
            margin-top: 48px;
            padding-top: 16px;
            border-top: 1px solid #e5e5e5;
            font-size: 9px;
            color: #aaa;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="header">
        <div>
            <div class="brand">Fragment Boutique</div>
            <div class="brand-sub">Compositions artisanales en bois</div>
        </div>
        <div class="invoice-meta">
            <div class="label">Facture</div>
            <div class="value">#{{ $order['orderNumber'] }}</div>
            <div class="date">{{ \Carbon\Carbon::parse($order['processedAt'])->locale('fr')->isoFormat('D MMMM YYYY') }}</div>
        </div>
    </div>

    @php
        $addr = $order['shippingAddress'] ?? null;
        $shipping = (float) $order['totalShippingPrice']['amount'];
        $tax = (float) $order['totalTax']['amount'];
    @endphp

    <div class="addresses">
        <div class="address-block">
            <div class="section-label">Client</div>
            <p class="name">{{ $customer['firstName'] }} {{ $customer['lastName'] }}</p>
            <p>{{ $customer['email'] }}</p>
        </div>

        @if ($addr)
            <div class="address-block">
                <div class="section-label">Adresse de livraison</div>
                <p class="name">{{ trim($addr['firstName'] . ' ' . $addr['lastName']) }}</p>
                <p>{{ $addr['address1'] }}</p>
                @if ($addr['address2'])
                    <p>{{ $addr['address2'] }}</p>
                @endif
                <p>{{ $addr['zip'] }} {{ $addr['city'] }}</p>
                @if ($addr['province'])
                    <p>{{ $addr['province'] }}</p>
                @endif
                <p>{{ $addr['country'] }}</p>
            </div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Article</th>
                <th style="text-align:right; width:60px;">Qté</th>
                <th style="text-align:right; width:90px;">Prix unitaire</th>
                <th style="text-align:right; width:90px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order['lineItems'] as $item)
                @php
                    $unitPrice = $item['price'] ? (float) $item['price']['amount'] : 0;
                    $lineTotal = $unitPrice * $item['quantity'];
                @endphp
                <tr>
                    <td>{{ $item['title'] }}</td>
                    <td style="text-align:right;">{{ $item['quantity'] }}</td>
                    <td style="text-align:right;">{{ number_format($unitPrice, 2, ',', ' ') }} €</td>
                    <td>{{ number_format($lineTotal, 2, ',', ' ') }} €</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row">
            <span class="totals-label">Sous-total</span>
            <span>{{ number_format((float) $order['subtotalPrice']['amount'], 2, ',', ' ') }} €</span>
        </div>
        <div class="totals-row">
            <span class="totals-label">Livraison</span>
            <span>{{ $shipping === 0.0 ? 'Offerte' : number_format($shipping, 2, ',', ' ') . ' €' }}</span>
        </div>
        @if ($tax > 0)
            <div class="totals-row">
                <span class="totals-label">TVA</span>
                <span>{{ number_format($tax, 2, ',', ' ') }} €</span>
            </div>
        @endif
        <div class="totals-divider"></div>
        <div class="totals-total">
            <span>Total</span>
            <span>{{ number_format((float) $order['totalPrice']['amount'], 2, ',', ' ') }} €</span>
        </div>
    </div>

    <div class="footer">
        Fragment Boutique — Merci pour votre commande.
    </div>

</body>
</html>
