<x-filament-panels::page>
    <form wire:submit="import">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-3">
            <x-filament::button type="submit">
                Import as drafts
            </x-filament::button>

            <x-filament::button color="gray" wire:click="mountAction('loadSample')" type="button">
                Load example
            </x-filament::button>
        </div>
    </form>

    <x-filament::section collapsible collapsed class="mt-6">
        <x-slot name="heading">What the JSON should look like</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <p>
                <code>title</code> and <code>price</code> are required. Everything else is optional.
                Prices are written as you would say them &mdash; <code>"5.99"</code>, not cents.
            </p>

            <p>
                <code>style</code>, <code>room</code> and <code>theme</code> take the slugs from your
                taxonomy (<code>mid-century</code>, <code>coffee-bar</code>&hellip;). An unrecognised
                value stops the import rather than creating a new one, so a typo never ends up in
                your filters.
            </p>

            <p>
                Other languages go under <code>translations</code>. Artwork and product files are not
                part of this &mdash; add them per product once the drafts exist.
            </p>

            <pre class="overflow-x-auto"><code>{{ \App\Actions\Products\ImportProductsFromJson::sample() }}</code></pre>
        </div>
    </x-filament::section>
</x-filament-panels::page>
