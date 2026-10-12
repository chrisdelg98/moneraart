@if ($products->isEmpty())
    <p class="py-10 text-muted">
        @if ($query !== '' || $selected !== [])
            Nothing matches that.
            <a href="{{ route('shop') }}" class="underline underline-offset-4">Clear the filters</a>.
        @else
            No artwork published yet.
        @endif
    </p>
@else
    <ul class="grid grid-cols-2 gap-x-4 gap-y-8 max-[359px]:grid-cols-1 sm:gap-x-6 sm:gap-y-10 md:grid-cols-3">
        @foreach ($products as $product)
            <li>
                <x-store.product-card :product="$product" />
            </li>
        @endforeach
    </ul>

    @if ($products->hasPages())
        {{-- data-page marks the block the script intercepts, so paging swaps
             the grid instead of reloading the whole page. --}}
        <nav data-page class="mt-12 border-t rule pt-6" aria-label="Pagination">
            {{ $products->onEachSide(1)->links() }}
        </nav>
    @endif
@endif
