<x-layouts.storefront title="Cart">
    <div class="mx-auto max-w-[1000px] px-4 py-12 sm:px-8">
        <h1 class="text-4xl sm:text-5xl">Cart</h1>

        @if (session('status'))
            <p role="status" class="mt-6 border-l-2 border-ink py-2 pl-4 text-sm">
                {{ session('status') }}
            </p>
        @endif

        @if ($products->isEmpty())
            <div class="mt-10 border-t rule pt-10">
                <p class="text-lg text-muted">Your cart is empty.</p>
                <a href="{{ route('shop') }}"
                   class="mt-6 inline-block border border-ink px-6 py-3 transition-colors hover:bg-ink hover:text-paper">
                    Browse the shop
                </a>
            </div>
        @else
            {{-- Items read as a list; the summary is a panel beside them, so
                 the checkout button is a button and not a full-width bar. --}}
            <div class="mt-10 grid gap-10 lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-14">
                <ul class="border-t rule">
                    @foreach ($products as $product)
                        @php $t = $product->translate(); @endphp
                        <li class="flex gap-5 border-b rule py-5">
                            <a href="{{ route('product', $t->slug) }}" class="w-20 shrink-0 sm:w-24">
                                <x-store.artwork :image="$product->coverImage" :title="$t->title" sizes="96px" />
                            </a>

                            <div class="flex min-w-0 flex-1 flex-col justify-between gap-2">
                                <div>
                                    <a href="{{ route('product', $t->slug) }}"
                                       class="font-display text-lg leading-snug hover:text-accent">
                                        {{ $t->title }}
                                    </a>
                                    <p class="label mt-1">
                                        {{ $product->file_count }} {{ Str::plural('file', $product->file_count) }}
                                        · instant download
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('cart.remove', $product->uuid) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-sm text-muted underline underline-offset-4 hover:text-ink">
                                        Remove
                                    </button>
                                </form>
                            </div>

                            <div class="shrink-0 text-right">
                                <p>{{ $product->effectivePrice()->format() }}</p>

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
                    <h2 id="summary" class="label">Summary</h2>

                    <div class="mt-4 border-t rule pt-4">
                        <p class="flex items-baseline justify-between text-lg">
                            <span>Subtotal</span>
                            <span>{{ $subtotal->format() }}</span>
                        </p>

                        <p class="mt-1 text-sm text-muted">Taxes, if any, are shown at checkout.</p>

                        <a href="{{ route('checkout') }}"
                           class="mt-6 block border border-ink bg-ink px-6 py-3.5 text-center text-paper transition-opacity hover:opacity-85">
                            Checkout
                        </a>

                        <p class="label mt-4 text-center">Instant download · No account needed</p>
                    </div>
                </aside>
            </div>
        @endif
    </div>
</x-layouts.storefront>
