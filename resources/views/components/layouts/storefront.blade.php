<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>

    @isset($description)
        <meta name="description" content="{{ $description }}">
    @endisset

    {{-- Kept off search engines until the owner turns indexing on (§11.5). --}}
    @unless(\App\Support\Facades\Settings::get('seo.allow_indexing'))
        <meta name="robots" content="noindex, nofollow">
    @endunless

    @vite(['resources/css/storefront.css', 'resources/js/storefront.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen antialiased">
    {{-- First in the tab order, by requirement (§12.4.1). --}}
    <a href="#main"
       class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-ink focus:px-4 focus:py-2 focus:text-paper">
        Skip to content
    </a>

    <header class="border-b rule">
        <div class="mx-auto flex max-w-[1400px] items-center justify-between gap-8 px-4 py-5 sm:px-8">
            <a href="{{ route('home') }}" class="font-display text-xl tracking-tight">
                {{ \App\Support\Facades\Settings::get('store.name') }}
            </a>

            <nav aria-label="Main" class="flex items-center gap-6 text-sm">
                <a href="{{ route('shop') }}" class="hover:text-accent">Shop</a>

                <a href="{{ route('cart') }}" class="flex items-center gap-1.5 hover:text-accent">
                    Cart
                    <span data-cart-count hidden
                          class="min-w-5 rounded-full bg-ink px-1.5 text-center text-xs leading-5 text-paper"></span>
                </a>
            </nav>
        </div>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    <footer class="mt-24 border-t rule">
        <div class="mx-auto max-w-[1400px] px-4 py-12 text-sm sm:px-8">
            <p class="max-w-prose text-muted">
                Artwork made with AI, selected and prepared by
                {{ \App\Support\Facades\Settings::get('store.name') }}.
            </p>

            <nav aria-label="Legal" class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-muted">
                <a href="#" class="hover:text-ink">Terms</a>
                <a href="#" class="hover:text-ink">Privacy</a>
                <a href="#" class="hover:text-ink">Refunds</a>
                <a href="#" class="hover:text-ink">How we work</a>
            </nav>
        </div>
    </footer>
</body>
</html>
