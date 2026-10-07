<x-layouts.storefront title="Checkout">
    <div class="mx-auto max-w-[1100px] px-4 py-12 sm:px-8">
        <h1 class="text-4xl sm:text-5xl">Checkout</h1>

        <div class="mt-10 grid gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">

            {{-- Three fields. Every one we do not collect is one we do not have
                 to encrypt, protect or justify. See §7.2. --}}
            <form id="checkout-form" class="flex flex-col gap-6"
                  @if ($isFree) method="POST" action="{{ route('checkout.free') }}" @else novalidate @endif>
                @csrf

                @if (session('error'))
                    <p role="alert" class="border-l-2 border-accent py-2 pl-3 text-sm">{{ session('error') }}</p>
                @endif

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
                        <a href="{{ route('legal', 'terms') }}" target="_blank" rel="noopener" class="underline underline-offset-4">Terms of Sale</a>
                        and
                        <a href="{{ route('legal', 'privacy') }}" target="_blank" rel="noopener" class="underline underline-offset-4">Privacy Policy</a>,
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

                @if ($isFree)
                    {{-- Nothing to charge, so nothing to load: the PayPal SDK
                         never reaches this page. See §7.6. --}}
                    <button type="submit"
                            class="w-full border border-ink bg-ink px-6 py-3.5 text-paper transition-opacity hover:opacity-85">
                        Get your free art
                    </button>
                    <p class="label text-center">No payment needed</p>
                @else
                <div class="border-t rule pt-6">
                    <p class="label">Payment</p>

                    <p id="checkout-error" role="alert" hidden
                       class="mt-3 border-l-2 border-accent py-2 pl-3 text-sm"></p>

                    @if ($paypalClientId)
                        <div id="paypal-buttons" class="mt-4 min-h-[3rem]"></div>
                    @else
                        <div class="mt-3 border rule px-6 py-8 text-center text-muted">
                            Payments are not switched on yet.
                        </div>
                    @endif
                </div>
                @endif
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

    @if ($paypalClientId && ! $isFree)
        <x-slot:head>
            {{-- The only third-party script on the storefront, and only on the
                 one page that needs it. --}}
            <script src="https://www.paypal.com/sdk/js?client-id={{ $paypalClientId }}&currency={{ $currency }}&intent=capture&disable-funding=paylater"
                    data-namespace="paypalSdk" defer></script>
        </x-slot:head>

        @push('scripts')
        @endpush

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
