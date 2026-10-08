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
                            <input type="text" id="coupon" name="coupon" autocomplete="off" class="field">
                            <button type="button"
                                    class="shrink-0 border border-ink px-5 text-sm transition-colors hover:bg-ink hover:text-paper">
                                Apply
                            </button>
                        </div>
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

                @if ($isFree)
                    {{-- Nothing to charge, so nothing to load: the PayPal SDK
                         never reaches this page. See §7.6. --}}
                    <section aria-labelledby="free">
                        <h2 id="free" class="label border-b rule pb-3">Confirm</h2>

                        <button type="submit" class="btn-accent mt-5 w-full px-6 py-3.5">
                            Get your free art
                        </button>

                        <p class="label mt-4 text-center">No payment needed</p>
                    </section>
                @else
                    <section aria-labelledby="payment">
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
                @endif
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

                    <div class="mt-5 flex items-baseline justify-between gap-4 border-t rule pt-5">
                        <span class="text-muted">Total</span>
                        <span class="font-display text-2xl">{{ $subtotal->format() }}</span>
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
