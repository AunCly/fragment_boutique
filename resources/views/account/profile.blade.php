@extends('layouts.shop')

@section('content')
<div class="max-w-3xl mx-auto py-12 px-4">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-10">
        <h1 class="font-serif text-3xl text-foreground">Mon profil</h1>
        <a href="{{ route('account.dashboard') }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
            ← Mes commandes
        </a>
    </div>

    {{-- Informations personnelles --}}
    <div class="border border-border rounded-2xl bg-card p-6 mb-6">
        <h2 class="font-serif text-base text-foreground mb-6">Informations personnelles</h2>

        @if (session('success'))
            <div class="mb-5 p-4 bg-green-50 border border-green-200 rounded-xl">
                <p class="font-sans-soft text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->hasAny(['email', 'first_name', 'last_name', 'password']))
            <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl">
                @foreach ($errors->only(['email', 'first_name', 'last_name', 'password']) as $error)
                    <p class="font-sans-soft text-sm text-red-600">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="font-sans-soft text-sm text-foreground block mb-1.5">Prénom</label>
                    <input
                        type="text"
                        name="first_name"
                        value="{{ old('first_name', $customer['firstName']) }}"
                        required
                        class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                    >
                </div>
                <div>
                    <label class="font-sans-soft text-sm text-foreground block mb-1.5">Nom</label>
                    <input
                        type="text"
                        name="last_name"
                        value="{{ old('last_name', $customer['lastName']) }}"
                        required
                        class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                    >
                </div>
            </div>

            <div>
                <label class="font-sans-soft text-sm text-foreground block mb-1.5">Adresse email</label>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email', $customer['email']) }}"
                    required
                    class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                >
            </div>

            <div class="pt-2 border-t border-border">
                <p class="font-sans-soft text-xs text-muted-foreground mb-4">Laissez vide pour ne pas modifier le mot de passe</p>

                <div class="space-y-4">
                    <div>
                        <label class="font-sans-soft text-sm text-foreground block mb-1.5">Nouveau mot de passe</label>
                        <input
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                        >
                    </div>
                    <div>
                        <label class="font-sans-soft text-sm text-foreground block mb-1.5">Confirmer le nouveau mot de passe</label>
                        <input
                            type="password"
                            name="password_confirmation"
                            autocomplete="new-password"
                            class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                        >
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button
                    type="submit"
                    class="px-6 py-2.5 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition"
                >
                    Enregistrer
                </button>
            </div>
        </form>
    </div>

    {{-- Adresse de livraison par défaut --}}
    <div class="border border-border rounded-2xl bg-card p-6">
        <h2 class="font-serif text-base text-foreground mb-6">Adresse de livraison par défaut</h2>

        @if (session('success_address'))
            <div class="mb-5 p-4 bg-green-50 border border-green-200 rounded-xl">
                <p class="font-sans-soft text-sm text-green-700">{{ session('success_address') }}</p>
            </div>
        @endif

        @if ($errors->hasAny(['address1', 'city', 'zip', 'country']))
            <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl">
                @foreach ($errors->only(['address1', 'city', 'zip', 'country']) as $error)
                    <p class="font-sans-soft text-sm text-red-600">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @php $addr = $customer['defaultAddress']; @endphp

        <form method="POST" action="{{ route('account.profile.address') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="font-sans-soft text-sm text-foreground block mb-1.5">Adresse</label>
                <input
                    type="text"
                    name="address1"
                    value="{{ old('address1', $addr['address1'] ?? '') }}"
                    required
                    placeholder="Numéro et nom de rue"
                    class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                >
            </div>

            <div>
                <label class="font-sans-soft text-sm text-foreground block mb-1.5">Complément d'adresse <span class="text-muted-foreground">(optionnel)</span></label>
                <input
                    type="text"
                    name="address2"
                    value="{{ old('address2', $addr['address2'] ?? '') }}"
                    placeholder="Appartement, bâtiment, etc."
                    class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                >
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="font-sans-soft text-sm text-foreground block mb-1.5">Code postal</label>
                    <input
                        type="text"
                        name="zip"
                        value="{{ old('zip', $addr['zip'] ?? '') }}"
                        required
                        class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                    >
                </div>
                <div>
                    <label class="font-sans-soft text-sm text-foreground block mb-1.5">Ville</label>
                    <input
                        type="text"
                        name="city"
                        value="{{ old('city', $addr['city'] ?? '') }}"
                        required
                        class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                    >
                </div>
            </div>

            <div>
                <label class="font-sans-soft text-sm text-foreground block mb-1.5">Pays</label>
                <input
                    type="text"
                    name="country"
                    value="{{ old('country', $addr['country'] ?? 'France') }}"
                    required
                    class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                >
            </div>

            <div>
                <label class="font-sans-soft text-sm text-foreground block mb-1.5">Téléphone <span class="text-muted-foreground">(optionnel)</span></label>
                <input
                    type="tel"
                    name="phone"
                    value="{{ old('phone', $addr['phone'] ?? '') }}"
                    class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
                >
            </div>

            <div class="flex justify-end pt-2">
                <button
                    type="submit"
                    class="px-6 py-2.5 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition"
                >
                    Enregistrer l'adresse
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
