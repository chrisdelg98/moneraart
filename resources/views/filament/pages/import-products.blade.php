<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Two ways in</x-slot>
            <x-slot name="description">
                Both create drafts. Artwork and product files are added per product afterwards.
            </x-slot>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h3 class="font-medium text-gray-950 dark:text-white">Spreadsheet</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Download the template, fill it in, upload it. Style, room and theme are
                        dropdowns, so a typo cannot reach your filters.
                    </p>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h3 class="font-medium text-gray-950 dark:text-white">JSON</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Paste an array. Better when something else is generating the catalog.
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{-- Each tab carries its own action button, so nothing on screen ever
             refers to the route you are not using. --}}
        {{ $this->form }}

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">What the columns mean</x-slot>

            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>
                    <strong>Title</strong> and <strong>Price</strong> are the only required columns.
                    Prices are written as you would say them &mdash; <code>5.99</code>, not cents.
                    Use <code>0</code> for a free product.
                </p>
                <p>
                    <strong>Style</strong>, <strong>Room</strong> and <strong>Theme</strong> come
                    from your taxonomy. The spreadsheet gives you dropdowns; in JSON they are slugs
                    such as <code>mid-century</code> or <code>coffee-bar</code>. Room takes several,
                    separated by commas. A value that is not recognised stops the import rather than
                    creating a new one, so a typo never ends up in your filters.
                </p>
                <p>
                    The <strong>(ES)</strong> columns hold the Spanish version. Leave them empty to
                    add it later.
                </p>
                <p class="text-gray-500">
                    Nothing is published. If one row has a problem, nothing is imported at all and
                    every problem is listed at once &mdash; so you fix the file in one pass.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
