<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Selling this month</x-slot>
        <x-slot name="description">Counted from what was paid, at the price it was paid at.</x-slot>

        <ul class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($this->getRows() as $row)
                <li class="flex items-center gap-4 py-2.5 first:pt-0 last:pb-0">
                    <span class="h-11 w-11 shrink-0 overflow-hidden rounded-md bg-gray-100 dark:bg-white/5">
                        @if ($row->cover)
                            <img src="{{ $row->cover->url() }}" alt=""
                                 class="h-full w-full object-cover" loading="lazy">
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        @if ($row->url)
                            <a href="{{ $row->url }}"
                               class="truncate text-sm font-medium text-gray-950 hover:text-primary-600 dark:text-white">
                                {{ $row->title }}
                            </a>
                        @else
                            {{-- Sold, then deleted from the catalogue. The sale
                                 still counts; there is just nowhere to go. --}}
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $row->title }}
                            </p>
                        @endif

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $row->sales }} {{ Str::plural('sale', $row->sales) }}
                        </p>
                    </div>

                    <span class="shrink-0 text-sm text-gray-950 dark:text-white">
                        {{ $row->revenue->format() }}
                    </span>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
