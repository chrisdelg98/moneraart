<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your orders</title>
</head>
<body style="margin:0;padding:0;background:#FAF7F0;color:#16140F;font-family:Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FAF7F0;padding:32px 16px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">

            <tr><td style="padding-bottom:28px;font-size:20px;letter-spacing:-0.01em;">
                {{ \App\Support\Facades\Settings::get('store.name') }}
            </td></tr>

            <tr><td style="padding-bottom:8px;font-size:28px;line-height:1.2;">Here are your orders.</td></tr>

            <tr><td style="padding-bottom:28px;color:#595751;font-size:15px;line-height:1.6;">
                Someone asked to see the orders placed with this address. If that
                was you, the link below opens them. If it wasn't, nothing has
                happened and you can ignore this &mdash; we've not changed anything.
            </td></tr>

            <tr><td style="padding-bottom:28px;">
                <a href="{{ $url }}"
                   style="display:inline-block;background:#16140F;color:#FAF7F0;text-decoration:none;padding:12px 22px;font-size:14px;">
                    View my orders
                </a>
            </td></tr>

            <tr><td style="padding-top:20px;border-top:1px solid #E2DDD2;color:#595751;font-size:13px;line-height:1.6;">
                The link works for one hour, then stops. Ask again from the site
                whenever you need a new one &mdash; there's no account and no
                password to remember.
            </td></tr>

        </table>
    </td></tr>
</table>
</body>
</html>
