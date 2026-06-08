@extends('layouts.shop')

@section('content')
<div class="max-w-md mx-auto py-16 px-4">
    <div class="text-center mb-10">
        <h1 class="font-serif text-3xl text-foreground">Mot de passe oublié</h1>
        <p class="font-sans-soft text-sm text-muted-foreground mt-2">
            Entrez votre email pour recevoir un lien de réinitialisation.
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

    <form method="POST" action="{{ route('account.password.reset') }}" class="space-y-4">
        @csrf

        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Adresse email</label>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autocomplete="email"
                class="w-full px-4 py-3 bg-muted border border-border rounded-xl font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            >
        </div>

        <button
            type="submit"
            class="w-full py-3 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition"
        >
            Envoyer le lien
        </button>
    </form>

    <div class="mt-6 text-center">
        <a href="{{ route('account.login') }}" class="font-sans-soft text-sm text-muted-foreground hover:text-foreground transition">
            ← Retour à la connexion
        </a>
    </div>
</div>
@endsection
