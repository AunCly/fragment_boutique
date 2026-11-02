@extends('layouts.shop')

@section('content')
<div class="max-w-md mx-auto py-16 px-4">
    <div class="text-center mb-10">
        <h1 class="font-serif text-3xl text-foreground">Activer mon compte</h1>
        <p class="font-sans-soft text-sm text-muted-foreground mt-2">
            Choisissez un mot de passe pour accéder à votre compte et à vos commandes.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
            @foreach ($errors->all() as $error)
                <p class="font-sans-soft text-sm text-red-600">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('account.activate') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="activation_url" value="{{ $activationUrl }}">

        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Mot de passe</label>
            <input
                type="password"
                name="password"
                required
                autocomplete="new-password"
                class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            >
            <p class="font-sans-soft text-xs text-muted-foreground mt-1">8 caractères minimum</p>
        </div>

        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Confirmer le mot de passe</label>
            <input
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            >
        </div>

        <button
            type="submit"
            class="w-full py-3 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition"
        >
            Activer mon compte
        </button>
    </form>
</div>
@endsection
