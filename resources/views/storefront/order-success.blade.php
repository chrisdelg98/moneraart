<x-layouts.storefront :title="'Order '.$order->number">
    <x-slot:head>
        {{-- Never indexed, never cached: it is one customer's receipt. --}}
        <meta name="robots" content="noindex, nofollow">
    </x-slot:head>

    <div class="mx-auto max-w-[800px] px-4 py-16 sm:px-8">
        @if ($order->status->isFulfillable())
            <p class="label">Order {{ $order->number }}</p>
            <h1 class="mt-4 text-4xl sm:text-5xl">Thank you.</h1>
            <p class="mt-5 text-lg text-muted">
                Your files are ready below, and we have emailed the same links to you.
            </p>
        @elseif ($order->status->needsAttention())
            <p class="label">Order {{ $order->number }}</p>
            <h1 class="mt-4 text-4xl">Almost there</h1>
            <p class="mt-5 max-w-prose text-lg text-muted">
                Your payment is going through a quick check on our side. We'll email your files as
                soon as it clears, usually within a few hours. Nothing further is needed from you.
            </p>
        @else
            <p class="label">Order {{ $order->number }}</p>
            <h1 class="mt-4 text-4xl">That payment didn't go through</h1>
            <p class="mt-5 text-lg text-muted">
                Nothing was charged. Your cart is still waiting whenever you'd like to try again.
            </p>
        @endif

        <ul class="mt-12 border-t rule">
            @foreach ($order->items as $item)
                <li class="flex items-center justify-between gap-6 border-b rule py-4">
                    <div>
                        <p>{{ $item->title_snapshot }}</p>
                        <p class="label mt-1">
                            {{ count($item->file_manifest) }}
                            {{ Str::plural('file', count($item->file_manifest)) }}
                        </p>
                    </div>

                    @if ($order->status->isFulfillable())
                        <span class="label">Downloads arrive by email</span>
                    @else
                        <span class="text-muted">{{ $item->total()->format() }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        <p class="mt-6 flex justify-between text-lg">
            <span>Total</span>
            <span>{{ $order->total()->format() }}</span>
        </p>

        <p class="label mt-12">
            Keep this page — the link works for 7 days. Your files are licensed for personal use.
        </p>
    </div>
</x-layouts.storefront>
