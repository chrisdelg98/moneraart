<x-layouts.storefront title="Cart">
    <div class="mx-auto max-w-[1100px] px-4 py-12 sm:px-8">
        <h1 class="text-4xl sm:text-5xl">Cart</h1>

        @if (session('status'))
            <p role="status" class="mt-6 border-l-2 border-ink bg-[color-mix(in_srgb,var(--color-rule)_30%,var(--color-paper))] py-3 pl-4">
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
            <ul class="mt-10 border-t rule">
                @foreach ($products as $product)
                    @php $t = $product->translate(); @endphp
                    <li class="flex items-start gap-5 border-b rule py-6">
                        <a href="{{ route('product', $t->slug) }}" class="w-24 shrink-0 sm:w-32">
                            <x-store.artwork :image="$product->coverImage" :title="$t->title" sizes="128px" />
                        </a>

                        <div class="flex-1">
                            <a href="{{ route('product', $t->slug) }}" class="font-display text-xl hover:text-accent">
                                {{ $t->title }}
                            </a>

                            <p class="label mt-1">
                                {{ $product->file_count }} {{ Str::plural('file', $product->file_count) }}
                                · instant download
                            </p>

                            <form method="POST" action="{{ route('cart.remove', $product->uuid) }}" class="mt-3">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-muted underline underline-offset-4 hover:text-ink">
                                    Remove
                                </button>
                            </form>
                        </div>

                        <div class="text-right">
                            <p>{{ $product->effectivePrice()->format() }}</p>

                            @if ($product->isOnSale())
                                <p class="text-sm text-muted line-through">{{ $product->price()->format() }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8 flex flex-col items-end gap-5">
                <p class="flex items-baseline gap-6 text-xl">
                    <span class="label">Subtotal</span>
                    <span>{{ $subtotal->format() }}</span>
                </p>

                <a href="{{ route('checkout') }}"
                   class="border border-ink bg-ink px-8 py-3.5 text-paper transition-opacity hover:opacity-85">
                    Checkout
                </a>

                <p class="label">Instant download · No account needed</p>
            </div>
        @endif
    </div>
</x-layouts.storefront>
