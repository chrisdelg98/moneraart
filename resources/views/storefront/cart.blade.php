<x-layouts.storefront title="Cart">
    <section class="band border-b rule">
        <div class="mx-auto max-w-[1100px] px-4 py-12 sm:px-8 lg:py-14">
            <h1 class="text-4xl sm:text-5xl">Cart</h1>
            <span class="mt-4 block h-0.5 w-14 bg-accent"></span>

            <p class="mt-5 text-muted">
                @if ($products->isEmpty())
                    Nothing here yet.
                @else
                    {{ $products->count() }} {{ Str::plural('piece', $products->count()) }}, ready to download.
                @endif
            </p>
        </div>
    </section>

    <div class="mx-auto max-w-[1100px] px-4 py-10 sm:px-8">
        <x-store.checkout-trail :step="1" />

        @if (session('status'))
            <p role="status" class="mt-8 border-l-2 border-accent bg-accent-soft py-3 pl-4 pr-3 text-sm">
                {{ session('status') }}
            </p>
        @endif

        @if ($products->isEmpty())
            <div class="mt-10 border rule px-6 py-16 text-center">
                <svg class="mx-auto h-10 w-10 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 6h15l-1.5 9h-12z" /><path d="M6 6 5 3H2" />
                    <circle cx="9" cy="19" r="1" /><circle cx="18" cy="19" r="1" />
                </svg>

                <p class="mt-5 font-display text-2xl">Your cart is empty.</p>
                <p class="mt-2 text-muted">Pick a piece and it lands here.</p>

                <a href="{{ route('shop') }}"
                   class="btn-accent mt-8 inline-flex items-center gap-3 px-7 py-3.5">
                    Browse the shop
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        @else
            {{-- Items read as a list; the summary is a panel beside them, so
                 the checkout button is a button and not a full-width bar. --}}
            <div class="mt-10 grid gap-10 lg:grid-cols-[minmax(0,1fr)_19rem] lg:gap-14">
                <ul class="border-t rule">
                    @foreach ($products as $product)
                        @php $t = $product->translate(); @endphp
                        <li class="flex gap-4 border-b rule py-5 sm:gap-6">
                            <a href="{{ route('product', $t->slug) }}"
                               class="w-24 shrink-0 overflow-hidden border rule sm:w-28">
                                <x-store.artwork :image="$product->coverImage" :title="$t->title" sizes="112px"
                                                 class="aspect-5/4 w-full object-cover" />
                            </a>

                            <div class="flex min-w-0 flex-1 flex-col justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('product', $t->slug) }}"
                                       class="font-display text-lg leading-snug hover:text-accent">
                                        {{ $t->title }}
                                    </a>

                                    <p class="label mt-1.5">
                                        {{ $product->file_count }} {{ Str::plural('file', $product->file_count) }}
                                        &middot; instant download
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('cart.remove', $product->uuid) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="-ml-1 inline-flex items-center gap-1.5 px-1 py-1 text-sm text-muted transition-colors hover:text-accent">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                                            <path d="m6 6 12 12M18 6 6 18" />
                                        </svg>
                                        Remove<span class="sr-only"> {{ $t->title }}</span>
                                    </button>
                                </form>
                            </div>

                            <div class="shrink-0 text-right">
                                <p class="font-display text-lg">{{ $product->effectivePrice()->format() }}</p>

                                @if ($product->isOnSale())
                                    <p class="text-sm text-muted line-through">
                                        {{ $product->price()->format() }}
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                <aside class="lg:sticky lg:top-8 lg:self-start" aria-labelledby="summary">
                    <div class="border rule bg-sand p-6">
                        <h2 id="summary" class="label">Summary</h2>

                        <div class="mt-5 flex items-baseline justify-between gap-4 border-t rule pt-5">
                            <span class="text-muted">Subtotal</span>
                            <span class="font-display text-2xl">{{ $subtotal->format() }}</span>
                        </div>

                        <p class="mt-2 text-sm text-muted">Taxes, if any, are shown at checkout.</p>

                        <a href="{{ route('checkout') }}"
                           class="btn-accent mt-6 flex items-center justify-center gap-3 px-6 py-3.5">
                            Checkout
                            <span aria-hidden="true">&rarr;</span>
                        </a>

                        <x-store.assurances class="mt-6 border-t rule pt-5" />
                    </div>

                    <a href="{{ route('shop') }}"
                       class="mt-5 inline-flex items-center gap-2 text-sm text-muted hover:text-ink">
                        <span aria-hidden="true">&larr;</span>
                        Continue shopping
                    </a>
                </aside>
            </div>
        @endif
    </div>
</x-layouts.storefront>
