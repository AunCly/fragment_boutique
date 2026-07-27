@extends('layouts.shop')

@section('content')
<div class="max-w-md mx-auto py-16 px-4">
    <div class="text-center mb-10">
        <h1 class="font-serif text-3xl text-foreground">Connexion</h1>
        <p class="font-sans-soft text-sm text-muted-foreground mt-2">
            Pas encore de compte ?
            <a href="{{ route('account.register') }}" class="text-foreground underline underline-offset-2 hover:opacity-70 transition">Créer un compte</a>
        </p>
    </div>

    @if (session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
            <p class="font-sans-soft text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
            @foreach ($errors->all() as $error)
                <p class="font-sans-soft text-sm text-red-600">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('account.login') }}" class="space-y-4">
        @csrf

        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Adresse email</label>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autocomplete="email"
                class="w-full px-4 py-3 bg-muted border border-border font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            >
        </div>

        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Mot de passe</label>
            <input
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="w-full px-4 py-3 bg-muted border border-border font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            >
        </div>

        <div class="flex justify-end">
            <a href="{{ route('account.password.reset') }}" class="font-sans-soft text-xs text-muted-foreground hover:text-foreground transition">Mot de passe oublié ?</a>
        </div>

        <button
            type="submit"
            class="w-full py-4 bg-primary text-background font-sans-soft text-sm uppercase hover:opacity-90 transition"
        >
            Se connecter
        </button>
    </form>
</div>
@endsection
