<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fragment Admin — @yield('title', 'Administration')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="fragment-theme antialiased bg-muted/30 min-h-screen">

    @auth
    <header class="bg-card border-b border-border sticky top-0 z-40">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <span class="font-serif text-lg text-foreground">Fragment <span class="text-muted-foreground text-sm font-sans-soft">Admin</span></span>
                <nav class="flex items-center gap-4">
                    <a href="{{ route('admin.dashboard') }}" class="font-sans-soft text-sm transition {{ request()->routeIs('admin.dashboard') ? 'text-foreground font-medium' : 'text-muted-foreground hover:text-foreground' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('admin.faqs.index') }}" class="font-sans-soft text-sm transition {{ request()->routeIs('admin.faqs.*') ? 'text-foreground font-medium' : 'text-muted-foreground hover:text-foreground' }}">
                        FAQ
                    </a>
                    <a href="{{ route('admin.wood-textures.index') }}" class="font-sans-soft text-sm transition {{ request()->routeIs('admin.wood-textures.*') ? 'text-foreground font-medium' : 'text-muted-foreground hover:text-foreground' }}">
                        Essences
                    </a>
                    <a href="{{ route('admin.formats.index') }}" class="font-sans-soft text-sm transition {{ request()->routeIs('admin.formats.*') ? 'text-foreground font-medium' : 'text-muted-foreground hover:text-foreground' }}">
                        Formats
                    </a>
                </nav>
            </div>
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="font-sans-soft text-xs text-muted-foreground hover:text-foreground transition">
                    Déconnexion
                </button>
            </form>
        </div>
    </header>
    @endauth

    <main class="max-w-5xl mx-auto px-4 py-10">
        @yield('content')
    </main>

</body>
</html>
