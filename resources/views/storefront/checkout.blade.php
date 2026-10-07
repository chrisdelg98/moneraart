<x-layouts.storefront title="Checkout">
    <div class="mx-auto max-w-[1100px] px-4 py-12 sm:px-8">
        <h1 class="text-4xl sm:text-5xl">Checkout</h1>

        <div class="mt-10 grid gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">

            {{-- Three fields. Every one we do not collect is one we do not have
                 to encrypt, protect or justify. See §7.2. --}}
            <form method="POST" action="#" class="flex flex-col gap-6">
                @csrf

                <div>
                    <label for="email" class="label">Your email</label>
                    <input type="email" id="email" name="email" required autocomplete="email"
                           class="mt-2 w-full border rule bg-transparent px-4 py-3"
                           aria-describedby="email-help">
                    <p id="email-help" class="mt-2 text-sm text-muted">
                        Where we send your files. We never share it.
                    </p>
                </div>

                <div>
                    <label for="coupon" class="label">Coupon code (optional)</label>
                    <div class="mt-2 flex gap-2">
                        <input type="text" id="coupon" name="coupon" autocomplete="off"
                               class="w-full border rule bg-transparent px-4 py-3">
                        <button type="button" class="shrink-0 border border-ink px-5">Apply</button>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    {{-- Unticked by default, and re-validated server-side. A
                         pre-ticked box is not consent. See §7.7.1. --}}
                    <input type="checkbox" id="terms" name="terms" value="1" required class="mt-1.5">
                    <label for="terms" class="text-sm">
                        I agree to the
                        <a href="#" target="_blank" rel="noopener" class="underline underline-offset-4">Terms of Sale</a>
                        and
                        <a href="#" target="_blank" rel="noopener" class="underline underline-offset-4">Privacy Policy</a>,
                        and I understand I'm buying files that arrive immediately and can't be returned.
                    </label>
                </div>

                <div class="flex items-start gap-3">
                    {{-- Separate and optional: bundling marketing consent into a
                         required box invalidates it under GDPR. --}}
                    <input type="checkbox" id="marketing" name="marketing" value="1" class="mt-1.5">
                    <label for="marketing" class="text-sm text-muted">
                        Email me when new artwork lands. Twice a month at most.
                    </label>
                </div>

                <div class="border-t rule pt-6">
                    <p class="label">Payment</p>
                    <div class="mt-3 border rule px-6 py-8 text-center text-muted">
                        PayPal arrives in the next step.
                    </div>
                </div>
            </form>

            <aside class="lg:sticky lg:top-8 lg:self-start" aria-labelledby="summary">
                <h2 id="summary" class="label">Order summary</h2>

                <ul class="mt-4 border-t rule">
                    @foreach ($products as $product)
                        <li class="flex justify-between gap-4 border-b rule py-3 text-sm">
                            <span>{{ $product->translate()->title }}</span>
                            <span class="shrink-0">{{ $product->effectivePrice()->format() }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-4 flex justify-between text-lg">
                    <span>Total</span>
                    <span>{{ $subtotal->format() }}</span>
                </p>

                <p class="label mt-6">Instant download · No account needed</p>
            </aside>
        </div>
    </div>
</x-layouts.storefront>
