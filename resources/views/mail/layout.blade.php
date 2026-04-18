<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $subject ?? config('laravel-auth.otp_channels.email.subject', 'Your verification code') }}</title>
</head>
<body style="margin:0;padding:24px;background:#f5f7fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:620px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="padding:32px;">
                            {{ $slot }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
