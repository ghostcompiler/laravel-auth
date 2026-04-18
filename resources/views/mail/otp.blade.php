@component('laravel-auth::mail.layout', ['subject' => config('laravel-auth.otp_channels.email.subject', 'Your verification code')])
    <h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;">Your verification code</h1>
    <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;">
        Use the code below to complete your verification. This code expires in {{ $expiresInMinutes }} minute{{ $expiresInMinutes === 1 ? '' : 's' }}.
    </p>
    <div style="margin:0 0 24px;padding:18px 20px;background:#eff6ff;border-radius:12px;border:1px solid #bfdbfe;font-size:32px;font-weight:700;letter-spacing:0.35em;text-align:center;color:#1d4ed8;">
        {{ $code }}
    </div>
    <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">
        If you did not request this code, you can safely ignore this message.
    </p>
@endcomponent
