<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your files are ready</title>
</head>
<body style="margin:0;padding:0;background:#FAF7F0;color:#16140F;font-family:Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FAF7F0;padding:32px 16px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">

            <tr><td style="padding-bottom:28px;font-size:20px;letter-spacing:-0.01em;">
                {{ \App\Support\Facades\Settings::get('store.name') }}
            </td></tr>

            <tr><td style="padding-bottom:8px;font-size:28px;line-height:1.2;">Your files are ready.</td></tr>

            <tr><td style="padding-bottom:28px;color:#595751;font-size:15px;line-height:1.6;">
                Order {{ $order->number }} — thank you. Each link below works for
                {{ $grants->first()?->max_downloads ?? 5 }} downloads and stays active until
                {{ $grants->first()?->expires_at?->format('j F, H:i') }} UTC.
            </td></tr>

            @foreach ($grants as $grant)
                <tr><td style="padding:14px 0;border-top:1px solid #E2DDD2;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size:15px;">
                                {{ $grant->file->name() }}
                                <div style="color:#595751;font-size:13px;padding-top:2px;">
                                    {{ strtoupper($grant->file->format) }}
                                    @if ($grant->file->ratio) · {{ $grant->file->ratio }} @endif
                                    · {{ $grant->file->humanSize() }}
                                </div>
                            </td>
                            <td align="right" style="padding-left:16px;">
                                {{-- The plaintext token exists here and nowhere else. --}}
                                <a href="{{ route('download', $grant->plainToken) }}"
                                   style="display:inline-block;background:#16140F;color:#FAF7F0;text-decoration:none;padding:10px 18px;font-size:14px;">
                                    Download
                                </a>
                            </td>
                        </tr>
                    </table>
                </td></tr>
            @endforeach

            <tr><td style="padding-top:28px;border-top:1px solid #E2DDD2;color:#595751;font-size:13px;line-height:1.6;">
                Links expired or used up? Open any of them and press
                <strong>Email me a new link</strong> — we'll send a fresh set, free.
            </td></tr>

            <tr><td style="padding-top:20px;color:#595751;font-size:13px;line-height:1.6;">
                Your files are licensed for personal use. Please keep them to yourself —
                it's what lets us keep prices where they are.
            </td></tr>

        </table>
    </td></tr>
</table>
</body>
</html>
