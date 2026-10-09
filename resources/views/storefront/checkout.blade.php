<x-layouts.storefront title="Checkout">
    <section class="band border-b rule">
        <div class="mx-auto max-w-[1100px] px-4 py-12 sm:px-8 lg:py-14">
            <h1 class="text-4xl sm:text-5xl">Checkout</h1>
            <span class="mt-4 block h-0.5 w-14 bg-accent"></span>

            <p class="mt-5 text-muted">
                @if ($isFree)
                    One email address, and the files are yours.
                @else
                    One email address, one payment, and the files are yours.
                @endif
            </p>
        </div>
    </section>

    <div class="mx-auto max-w-[1100px] px-4 py-10 sm:px-8">
        <x-store.checkout-trail :step="2" />

        <div class="mt-10 grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,20rem)] lg:gap-14">

            {{-- Three fields. Every one we do not collect is one we do not have
                 to encrypt, protect or justify. See §7.2. --}}
            <form id="checkout-form" class="flex flex-col gap-10"
                  @if ($isFree) method="POST" action="{{ route('checkout.free') }}" @else novalidate @endif>
                @csrf

                @if (session('error'))
                    <p role="alert" class="border-l-2 border-accent bg-accent-soft py-3 pl-4 pr-3 text-sm">
                        {{ session('error') }}
                    </p>
                @endif

                <section aria-labelledby="delivery">
                    <h2 id="delivery" class="label border-b rule pb-3">Delivery</h2>

                    <div class="mt-5">
                        <label for="email" class="block text-sm">Your email</label>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               placeholder="you@example.com"
                               class="field mt-2" aria-describedby="email-help">
                        <p id="email-help" class="mt-2 text-sm text-muted">
                            Where we send your files. We never share it.
                        </p>
                    </div>

                    <div class="mt-6">
                        <label for="coupon" class="block text-sm">Coupon code <span class="text-muted">(optional)</span></label>

                        <div class="mt-2 flex gap-2">
                            <input type="text" id="coupon" name="coupon" autocomplete="off" class="field"
                                   aria-describedby="coupon-message">
                            <button type="button" id="coupon-apply"
                                    class="shrink-0 border border-ink px-5 text-sm transition-colors hover:bg-ink hover:text-paper">
                                Apply
                            </button>
                        </div>

                        {{-- One live region for both answers: a rejection and an
                             acceptance are the same news to a screen reader, and
                             two regions would fight over which speaks. --}}
                        <p id="coupon-message" role="status" aria-live="polite" hidden
                           class="mt-2 text-sm"></p>
                    </div>
                </section>

                <section aria-labelledby="agreement">
                    <h2 id="agreement" class="label border-b rule pb-3">Agreement</h2>

                    <div class="mt-5 flex items-start gap-3">
                        {{-- Unticked by default, and re-validated server-side. A
                             pre-ticked box is not consent. See §7.7.1. --}}
                        <input type="checkbox" id="terms" name="terms" value="1" required class="mt-1.5 h-4 w-4 shrink-0">
                        <label for="terms" class="text-sm">
                            I agree to the
                            <a href="{{ route('legal', 'terms') }}" target="_blank" rel="noopener" class="underline underline-offset-4 hover:text-accent">Terms of Sale</a>
                            and
                            <a href="{{ route('legal', 'privacy') }}" target="_blank" rel="noopener" class="underline underline-offset-4 hover:text-accent">Privacy Policy</a>,
                            and I understand I'm buying files that arrive immediately and can't be returned.
                        </label>
                    </div>

                    <div class="mt-4 flex items-start gap-3">
                        {{-- Separate and optional: bundling marketing consent into a
                             required box invalidates it under GDPR. --}}
                        <input type="checkbox" id="marketing" name="marketing" value="1" class="mt-1.5 h-4 w-4 shrink-0">
                        <label for="marketing" class="text-sm text-muted">
                            Email me when new artwork lands. Twice a month at most.
                        </label>
                    </div>
                </section>

                {{-- Both sections are always in the page, because a coupon can
                     take a paid cart to nothing and PayPal refuses an order of
                     0.00. When the cart is free to begin with the SDK is never
                     loaded at all. See §7.6. --}}
                <section id="free-section" aria-labelledby="free" @unless ($isFree) hidden @endunless>
                    <h2 id="free" class="label border-b rule pb-3">Confirm</h2>

                    <button type="submit" class="btn-accent mt-5 w-full px-6 py-3.5">
                        Get your free art
                    </button>

                    <p class="label mt-4 text-center">No payment needed</p>
                </section>

                @unless ($isFree)
                    <section id="payment-section" aria-labelledby="payment">
                        <h2 id="payment" class="label border-b rule pb-3">Payment</h2>

                        <p id="checkout-error" role="alert" hidden
                           class="mt-5 border-l-2 border-accent bg-accent-soft py-3 pl-4 pr-3 text-sm"></p>

                        @if ($paypalClientId)
                            <div id="paypal-buttons" class="mt-5 min-h-12"></div>

                            <p class="mt-4 text-sm text-muted">
                                Pay with a debit or credit card, or with your PayPal
                                balance. No PayPal account needed for a card.
                            </p>
                        @else
                            <div class="mt-5 border rule px-6 py-8 text-center text-muted">
                                Payments are not switched on yet.
                            </div>
                        @endif
                    </section>
                @endunless
            </form>

            <aside class="lg:sticky lg:top-8 lg:self-start" aria-labelledby="summary">
                <div class="border rule bg-sand p-6">
                    <h2 id="summary" class="label">Order summary</h2>

                    <ul class="mt-5 space-y-4 border-t rule pt-5">
                        @foreach ($products as $product)
                            @php $t = $product->translate(); @endphp
                            <li class="flex items-center gap-3">
                                <span class="w-14 shrink-0 overflow-hidden border rule">
                                    <x-store.artwork :image="$product->coverImage" :title="$t->title" sizes="56px"
                                                     class="aspect-5/4 w-full object-cover" />
                                </span>

                                <span class="min-w-0 flex-1 text-sm leading-snug">{{ $t->title }}</span>

                                <span class="shrink-0 text-sm">{{ $product->effectivePrice()->format() }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div id="summary-discount" hidden
                         class="mt-5 flex items-baseline justify-between gap-4 border-t rule pt-5 text-sm">
                        <span class="text-muted">Discount <span data-coupon-code></span></span>
                        <span class="text-accent" data-discount></span>
                    </div>

                    <div class="mt-5 flex items-baseline justify-between gap-4 border-t rule pt-5">
                        <span class="text-muted">Total</span>
                        <span class="font-display text-2xl" data-total>{{ $subtotal->format() }}</span>
                    </div>

                    <x-store.assurances :paid="! $isFree" class="mt-6 border-t rule pt-5" />
                </div>

                <a href="{{ route('cart') }}"
                   class="mt-5 inline-flex items-center gap-2 text-sm text-muted hover:text-ink">
                    <span aria-hidden="true">&larr;</span>
                    Back to cart
                </a>
            </aside>
        </div>
    </div>

    {{-- The coupon field, on both paths: a code can apply to a free cart's
         sibling just as well as to a paid one, and this script is the only
         thing on the page that is not about taking money. --}}
    <script>
        (() => {
            const form = document.getElementById('checkout-form');
            const input = document.getElementById('coupon');
            const button = document.getElementById('coupon-apply');
            const message = document.getElementById('coupon-message');
            const row = document.getElementById('summary-discount');

            if (!form || !input || !button) return;

            const say = (text, isError) => {
                message.textContent = text;
                message.hidden = false;
                message.className = 'mt-2 text-sm ' + (isError ? 'text-accent' : 'text-muted');
            };

            // Nothing here changes a price. The server recomputes the discount
            // inside the transaction that writes the order; this only shows
            // the customer what to expect.
            const preview = (data) => {
                row.querySelector('[data-coupon-code]').textContent = data.code ?? '';
                row.querySelector('[data-discount]').textContent = '−' + data.discount;
                document.querySelector('[data-total]').textContent = data.total;
                row.hidden = false;
            };

            // A code that covers the whole order leaves nothing to charge, and
            // PayPal rejects an order of 0.00 — so the page changes path.
            const goFree = () => {
                form.method = 'POST';
                form.action = @json(route('checkout.free'));
                form.removeAttribute('novalidate');
                document.getElementById('payment-section')?.setAttribute('hidden', '');
                document.getElementById('free-section')?.removeAttribute('hidden');
            };

            button.addEventListener('click', async () => {
                const code = input.value.trim();

                if (!code) return input.focus();

                button.disabled = true;

                try {
                    const response = await fetch(@json(route('checkout.coupon')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                        },
                        body: JSON.stringify({
                            coupon: code,
                            email: form.querySelector('#email')?.value || null,
                        }),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok || !data.valid) {
                        row.hidden = true;
                        document.querySelector('[data-total]').textContent = @json($subtotal->format());
                        return say(data.message || 'That code could not be applied.', true);
                    }

                    preview(data);
                    say(data.message, false);

                    if (data.totalCents === 0) {
                        goFree();
                    }
                } catch {
                    say('We could not check that code just now. Try again.', true);
                } finally {
                    button.disabled = false;
                }
            });

            // Typing a new code invalidates the last answer.
            input.addEventListener('input', () => { message.hidden = true; });
        })();
    </script>

    @if ($paypalClientId && ! $isFree)
        <x-slot:head>
            {{-- The only third-party script on the storefront, and only on the
                 one page that needs it.

                 enable-funding=card asks for the card button explicitly, so a
                 buyer without a PayPal account still has a way to pay; Pay
                 Later is off because it is a credit offer we do not make. --}}
            <script src="https://www.paypal.com/sdk/js?client-id={{ $paypalClientId }}&currency={{ $currency }}&intent=capture&enable-funding=card&disable-funding=paylater"
                    data-namespace="paypalSdk" defer></script>
        </x-slot:head>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('checkout-form');
                const errorBox = document.getElementById('checkout-error');
                const token = form.querySelector('input[name="_token"]').value;

                const fail = (message) => {
                    errorBox.textContent = message;
                    errorBox.hidden = false;
                };

                const post = async (url, body) => {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify(body),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(data.message || 'Something went wrong. Please try again.');
                    }

                    return data;
                };

                const render = () => {
                    if (!window.paypalSdk) {
                        return fail('PayPal did not load. Check your connection and refresh.');
                    }

                    window.paypalSdk.Buttons({
                        style: { layout: 'vertical', shape: 'rect', label: 'pay' },

                        onClick: (data, actions) => {
                            errorBox.hidden = true;

                            // The browser never decides what anything costs — it
                            // only says who is buying and that they agreed.
                            if (!form.reportValidity()) {
                                return actions.reject();
                            }

                            return actions.resolve();
                        },

                        createOrder: async () => {
                            const payload = new FormData(form);

                            const data = await post(@json(route('checkout.paypal.create')), {
                                email: payload.get('email'),
                                terms: payload.get('terms') ? 1 : 0,
                                marketing: payload.get('marketing') ? 1 : 0,
                                // Checked again server-side. If it lapsed while
                                // the page was open, the order is refused here
                                // rather than charged at the wrong price.
                                coupon: payload.get('coupon') || null,
                            });

                            sessionStorage.setItem('orderUuid', data.orderUuid);

                            return data.paypalOrderId;
                        },

                        onApprove: async (data) => {
                            const result = await post(@json(route('checkout.paypal.capture')), {
                                orderUuid: sessionStorage.getItem('orderUuid'),
                                paypalOrderId: data.orderID,
                            });

                            window.location.href = result.redirect;
                        },

                        onError: (err) => {
                            fail(err?.message || 'We could not complete that payment. Please try again.');
                        },
                    }).render('#paypal-buttons');
                };

                // The SDK is deferred, so it may not be ready at DOMContentLoaded.
                window.paypalSdk ? render() : window.addEventListener('load', render);
            });
        </script>
    @endif
</x-layouts.storefront>
