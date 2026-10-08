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
        <div class="mx-auto flex max-w-[1400px] items-center justify-between gap-8 px-4 py-4 sm:px-8">
            <a href="{{ route('home') }}" class="font-display text-xl tracking-tight">
                {{ \App\Support\Facades\Settings::get('store.name') }}
            </a>

            <nav aria-label="Main" class="hidden items-center gap-8 text-sm md:flex">
                @foreach ([['home', 'Home'], ['shop', 'Shop']] as [$name, $label])
                    <a href="{{ route($name) }}"
                       @class([
                           'pb-1 hover:text-accent',
                           'border-b-2 border-accent text-accent' => request()->routeIs($name),
                       ])
                       @if (request()->routeIs($name)) aria-current="page" @endif>
                        {{ $label }}
                    </a>
                @endforeach

                <a href="{{ route('legal', 'how-we-work') }}"
                   @class(['pb-1 hover:text-accent', 'border-b-2 border-accent text-accent' => request()->fullUrlIs(route('legal', 'how-we-work'))])>
                    About
                </a>
            </nav>

            <div class="flex items-center gap-5">
                <a href="{{ route('shop') }}" class="hover:text-accent" aria-label="Search artwork">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                    </svg>
                </a>

                <a href="{{ route('cart') }}" class="relative hover:text-accent" aria-label="Cart">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 6h15l-1.5 9h-12z" /><path d="M6 6 5 3H2" />
                        <circle cx="9" cy="19" r="1" /><circle cx="18" cy="19" r="1" />
                    </svg>
                    <span data-cart-count hidden
                          class="absolute -right-2 -top-2 min-w-[1.1rem] rounded-full bg-accent px-1 text-center text-[0.65rem] leading-[1.1rem] text-paper"></span>
                </a>
            </div>
        </div>
    </header>

    <main id="main" class="relative">
        {{ $slot }}
    </main>

    <button type="button" id="to-top" class="to-top" hidden aria-label="Back to top">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 19V6m0 0-6 6m6-6 6 6" />
        </svg>
    </button>

    <footer class="band-sand mt-20 border-t rule">
        <div class="mx-auto max-w-[1400px] px-4 py-14 sm:px-8">
            <div class="flex flex-wrap justify-between gap-10">
                <div>
                    <p class="font-display text-xl">
                        {{ \App\Support\Facades\Settings::get('store.name') }}
                    </p>
                    <p class="label mt-1">Printable wall art</p>
                    <p class="mt-4 max-w-xs text-sm text-muted">
                        Art that brings beauty to your space. Made with AI,
                        selected and prepared by us.
                    </p>
                </div>

                <nav aria-label="Footer" class="flex flex-wrap gap-x-10 gap-y-2 text-sm">
                    <a href="{{ route('shop') }}" class="text-muted hover:text-ink">Shop</a>
                    <a href="{{ route('legal', 'how-we-work') }}" class="text-muted hover:text-ink">About</a>
                    <a href="{{ route('legal', 'terms') }}" class="text-muted hover:text-ink">Terms</a>
                    <a href="{{ route('legal', 'privacy') }}" class="text-muted hover:text-ink">Privacy</a>
                    <a href="{{ route('legal', 'refunds') }}" class="text-muted hover:text-ink">Refunds</a>
                </nav>
            </div>

            <p class="mt-12 border-t rule pt-6 text-sm text-muted">
                &copy; {{ date('Y') }} {{ \App\Support\Facades\Settings::get('store.name') }}.
                All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html>
