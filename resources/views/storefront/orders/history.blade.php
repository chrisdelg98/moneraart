<x-layouts.storefront title="Your orders">
    <section class="band border-b rule">
        <div class="mx-auto max-w-[1100px] px-4 py-12 sm:px-8 lg:py-14">
            <h1 class="text-4xl sm:text-5xl">Your orders</h1>
            <span class="mt-4 block h-0.5 w-14 bg-accent"></span>
            <p class="mt-5 text-muted">{{ $customer->email }}</p>
        </div>
    </section>

    <div class="mx-auto max-w-[1100px] px-4 py-12 sm:px-8">
        @if ($orders->isEmpty())
            <p class="text-muted">Nothing here yet.</p>
        @else
            <ul class="border-t rule">
                @foreach ($orders as $order)
                    <li class="border-b rule py-6">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
                            <div>
                                <p class="font-display text-lg">Order {{ $order->number }}</p>
                                <p class="label mt-1">
                                    {{ $order->paid_at?->format('j F Y') }}
                                    &middot; {{ $order->total()->format() }}
                                </p>
                            </div>

                            @if ($order->status->isFulfillable())
                                {{-- Signed per order: this page cannot be used
                                     to reach one that is not on it. --}}
                                <form method="POST" action="{{ $resendUrl($order) }}">
                                    @csrf
                                    <button type="submit"
                                            class="border border-ink px-5 py-2.5 text-sm transition-colors hover:bg-ink hover:text-paper">
                                        Email me the files again
                                    </button>
                                </form>
                            @else
                                <span class="label">{{ $order->status->label() }}</span>
                            @endif
                        </div>

                        <ul class="mt-4 space-y-1 text-sm text-muted">
                            @foreach ($order->items as $item)
                                {{-- The snapshot, so a piece renamed or removed
                                     since the sale still reads as what was
                                     bought. --}}
                                <li>{{ $item->title_snapshot }} &middot; {{ $item->total()->format() }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>

            <p class="mt-8 text-sm text-muted">
                Sending a fresh set stops the older links for that order working.
                That is on purpose &mdash; it means a link you forwarded or lost
                can be taken back.
            </p>
        @endif
    </div>
</x-layouts.storefront>
