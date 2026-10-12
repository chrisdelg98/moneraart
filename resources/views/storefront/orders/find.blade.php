<x-layouts.storefront title="Find your orders">
    <section class="band border-b rule">
        <div class="mx-auto max-w-[1100px] px-4 py-12 sm:px-8 lg:py-14">
            <h1 class="text-4xl sm:text-5xl">Your orders</h1>
            <span class="mt-4 block h-0.5 w-14 bg-accent"></span>
            <p class="mt-5 max-w-xl text-muted">
                No account, no password. Tell us the address you bought with and
                we'll email you a link to everything you've ordered.
            </p>
        </div>
    </section>

    <div class="mx-auto max-w-[1100px] px-4 py-12 sm:px-8">
        <div class="max-w-md">
            <form method="POST" action="{{ route('orders.find.send') }}">
                @csrf

                <label for="email" class="block text-sm">Your email</label>

                <input type="email" id="email" name="email" required autocomplete="email"
                       value="{{ old('email') }}" placeholder="you@example.com"
                       class="field mt-2">

                @error('email')
                    <p role="alert" class="mt-2 text-sm text-accent">{{ $message }}</p>
                @enderror

                <button type="submit" class="btn-accent mt-6 w-full px-6 py-3.5">
                    Email me the link
                </button>
            </form>

            <p class="mt-8 border-t rule pt-6 text-sm text-muted">
                The link lands in your inbox and works for an hour. We'll never
                say on this page whether an address has orders &mdash; that way
                nobody can use it to find out who has bought from us.
            </p>
        </div>
    </div>
</x-layouts.storefront>
