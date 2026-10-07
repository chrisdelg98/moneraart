<x-layouts.storefront :title="$body->title">
    <article class="mx-auto max-w-[46rem] px-4 py-14 sm:px-8">
        <h1 class="text-4xl sm:text-5xl">{{ $body->title }}</h1>

        {{-- Which revision this is, stated plainly: a customer in a dispute
             needs to know what they agreed to and when. See §7.7.2. --}}
        <p class="label mt-4">
            Version {{ $version->version }} · in force since
            {{ $version->effective_at->format('j F Y') }}
        </p>

        <div class="legal mt-10 leading-relaxed">
            {!! Str::markdown($body->body) !!}
        </div>
    </article>
</x-layouts.storefront>
