<!DOCTYPE html>
<html lang="{{ $order->locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order {{ $order->number }} confirmed</title>
</head>
<body style="margin:0;padding:0;background:#FAF7F0;color:#16140F;font-family:Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FAF7F0;padding:32px 16px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">

            <tr><td style="padding-bottom:28px;font-size:20px;letter-spacing:-0.01em;">
                {{ \App\Support\Facades\Settings::get('store.name') }}
            </td></tr>

            <tr><td style="padding-bottom:8px;font-size:28px;line-height:1.2;">Payment received.</td></tr>

            <tr><td style="padding-bottom:28px;color:#595751;font-size:15px;line-height:1.6;">
                @if ($isHeld)
                    Thank you. We're checking this order by hand before releasing
                    the files — it's quick, and there's nothing for you to do.
                    Your download links follow by email shortly.
                @else
                    Thank you. Your download links are on their way in a separate
                    email — if it hasn't arrived in a few minutes, check your spam
                    folder.
                @endif
            </td></tr>

            <tr><td style="padding-bottom:6px;border-top:1px solid #E2DDD2;padding-top:20px;
                           font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#595751;">
                Order {{ $order->number }}
            </td></tr>

            <tr><td style="padding-bottom:16px;color:#595751;font-size:13px;">
                {{ $order->paid_at?->format('j F Y, H:i') }} UTC
            </td></tr>

            @foreach ($order->items as $item)
                <tr><td style="padding:12px 0;border-top:1px solid #E2DDD2;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            {{-- The snapshot, never the live product: a title or
                                 price edited later must not rewrite a receipt. --}}
                            <td style="font-size:15px;line-height:1.4;">{{ $item->title_snapshot }}</td>
                            <td align="right" style="padding-left:16px;font-size:15px;white-space:nowrap;">
                                {{ $item->total()->format() }}
                            </td>
                        </tr>
                    </table>
                </td></tr>
            @endforeach

            @if ($order->discount()->cents > 0)
                <tr><td style="padding:12px 0;border-top:1px solid #E2DDD2;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size:15px;color:#595751;">
                                Discount
                                @if ($order->coupon_code)
                                    ({{ $order->coupon_code }})
                                @endif
                            </td>
                            <td align="right" style="padding-left:16px;font-size:15px;white-space:nowrap;color:#595751;">
                                &minus;{{ $order->discount()->format() }}
                            </td>
                        </tr>
                    </table>
                </td></tr>
            @endif

            <tr><td style="padding:16px 0;border-top:2px solid #16140F;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size:17px;">Total</td>
                        <td align="right" style="padding-left:16px;font-size:17px;white-space:nowrap;">
                            {{ $order->total()->format() }}
                        </td>
                    </tr>
                </table>
            </td></tr>

            @if ($order->payment)
                <tr><td style="padding-bottom:28px;color:#595751;font-size:13px;line-height:1.6;">
                    Paid with {{ Str::headline($order->payment->provider) }}.
                </td></tr>
            @endif

            <tr><td style="padding-bottom:28px;">
                <a href="{{ route('order.success', $order->uuid) }}"
                   style="display:inline-block;background:#16140F;color:#FAF7F0;text-decoration:none;padding:12px 22px;font-size:14px;">
                    View your order
                </a>
            </td></tr>

            <tr><td style="padding-top:20px;border-top:1px solid #E2DDD2;color:#595751;font-size:13px;line-height:1.6;">
                Keep this email — it's your receipt. Digital files can't be
                returned once they've been sent, which is why we say so before
                you pay. If something isn't right, reply to this email and we'll
                sort it out.
            </td></tr>

        </table>
    </td></tr>
</table>
</body>
</html>
