<x-layouts.storefront title="Download link">
    <x-slot:head><meta name="robots" content="noindex, nofollow"></x-slot:head>

    <div class="mx-auto max-w-[640px] px-4 py-24 sm:px-8">
        @if ($reason === 'expired')
            <h1 class="text-4xl">This link has expired</h1>
            <p class="mt-5 text-lg text-muted">
                Download links stay active for a limited time. We'll happily send you a fresh one.
            </p>
        @elseif ($reason === 'limit')
            <h1 class="text-4xl">This link has been used up</h1>
            <p class="mt-5 text-lg text-muted">
                Each link allows a set number of downloads. We'll send you a fresh one.
            </p>
        @elseif ($reason === 'revoked')
            <h1 class="text-4xl">This link is no longer active</h1>
            <p class="mt-5 text-lg text-muted">
                If you think that's a mistake, write to us and we'll sort it out.
            </p>
        @else
            <h1 class="text-4xl">We couldn't find that link</h1>
            <p class="mt-5 text-lg text-muted">
                Check that you copied the whole address from your email. If the
                email itself is gone, you can reach the order without it.
            </p>
        @endif

        @if ($grant)
            {{-- No support ticket needed, and no way for a stranger to trigger
                 it: the new link only ever goes to the original buyer's inbox. --}}
            <form method="POST" action="{{ route('download.renew', $grant->uuid) }}" class="mt-8">
                @csrf
                <button type="submit"
                        class="border border-ink bg-ink px-6 py-3 text-paper transition-opacity hover:opacity-85">
                    Email me a new link
                </button>
            </form>

            <p class="label mt-4">It goes to the address you bought with.</p>
        @else
            {{-- Without a grant there is nothing to renew, and until now this
                 page was a dead end — which is to say, a support email. --}}
            <a href="{{ route('orders.find') }}"
               class="mt-8 inline-block border border-ink bg-ink px-6 py-3 text-paper transition-opacity hover:opacity-85">
                Find my orders
            </a>

            <p class="label mt-4">We'll email you a link. No account needed.</p>
        @endif

        <p class="mt-10 flex flex-wrap gap-x-6 gap-y-2">
            <a href="{{ route('home') }}" class="underline underline-offset-4">Back to the shop</a>

            @if ($grant)
                <a href="{{ route('orders.find') }}" class="text-muted underline underline-offset-4 hover:text-ink">
                    See everything I've bought
                </a>
            @endif
        </p>
    </div>
</x-layouts.storefront>
