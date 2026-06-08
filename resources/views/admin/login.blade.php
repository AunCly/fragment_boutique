@extends('layouts.admin')

@section('title', 'Connexion')

@section('content')
<div class="max-w-sm mx-auto py-20 px-4">
    <div class="text-center mb-10">
        <h1 class="font-serif text-3xl text-foreground">Fragment Admin</h1>
        <p class="font-sans-soft text-sm text-muted-foreground mt-2">Espace d'administration</p>
    </div>

    @if ($errors->any())
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
            @foreach ($errors->all() as $error)
                <p class="font-sans-soft text-sm text-red-600">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.login') }}" class="space-y-4">
        @csrf

        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Email</label>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="email"
                class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            >
        </div>

        <div>
            <label class="font-sans-soft text-sm text-foreground block mb-1.5">Mot de passe</label>
            <input
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="w-full px-4 py-3 bg-card border border-border rounded-xl font-sans-soft text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition"
            >
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="remember" id="remember" class="rounded border-border">
            <label for="remember" class="font-sans-soft text-sm text-muted-foreground">Se souvenir de moi</label>
        </div>

        <button
            type="submit"
            class="w-full py-3 bg-primary text-primary-foreground rounded-full font-sans-soft text-sm font-medium hover:opacity-90 transition"
        >
            Se connecter
        </button>
    </form>
</div>
@endsection
