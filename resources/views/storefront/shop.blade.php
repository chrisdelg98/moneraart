<x-layouts.storefront title="Shop">
    <div class="mx-auto max-w-[1400px] px-4 py-12 sm:px-8">
        <h1 class="text-4xl sm:text-5xl">Shop</h1>

        @if ($products->isEmpty())
            <p class="mt-6 text-muted">No artwork published yet.</p>
        @else
            <ul class="mt-12 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $i => $product)
                    @php $t = $product->translate(); @endphp
                    <li>
                        <a href="{{ route('product', $t->slug) }}" class="group block">
                            <x-store.artwork :image="$product->coverImage" :title="$t->title"
                                             sizes="(max-width: 640px) 100vw, 420px"
                                             class="transition-opacity group-hover:opacity-90" />
                            <x-store.wall-label :product="$product"
                                                :index="$products->firstItem() + $i"
                                                heading="h2" class="mt-5" />
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-16">{{ $products->links() }}</div>
        @endif
    </div>
</x-layouts.storefront>
