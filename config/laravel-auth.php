<?php

return [
    'middleware' => [
        'web',
        'auth',
    ],

    'enforce_2fa' => (bool) env('LARAVEL_AUTH_ENFORCE_2FA', true),

    'enforce_middleware_groups' => [
        'web',
    ],

    'verification_url' => env('LARAVEL_AUTH_VERIFICATION_URL'),

    'preset' => env('LARAVEL_AUTH_PRESET'),

    'proof_ttl_seconds' => 300,

    'otp_channels' => [
        'length' => 6,
        'ttl_seconds' => 300,
        'max_attempts' => 5,

        'email' => [
            'enabled' => true,
            'provider' => 'mail',
            'view' => 'laravel-auth::mail.otp',
            'layout' => 'laravel-auth::mail.layout',
            'subject' => 'Your verification code',
        ],

        'sms' => [
            'enabled' => true,
            'provider' => env('LARAVEL_AUTH_SMS_PROVIDER', 'twilio'),
            'from' => env('LARAVEL_AUTH_SMS_FROM'),
            'view' => 'laravel-auth::messages.sms',
            'custom_transport' => \GhostCompiler\LaravelAuth\OTP\Transport\CustomSmsOtpTransport::class,
            'providers' => [
                'twilio' => [
                    'account_sid' => env('TWILIO_ACCOUNT_SID'),
                    'auth_token' => env('TWILIO_AUTH_TOKEN'),
                    'from' => env('TWILIO_SMS_FROM'),
                ],
                'vonage' => [
                    'key' => env('VONAGE_KEY'),
                    'secret' => env('VONAGE_SECRET'),
                    'from' => env('VONAGE_SMS_FROM'),
                ],
                'messagebird' => [
                    'access_key' => env('MESSAGEBIRD_ACCESS_KEY'),
                    'originator' => env('MESSAGEBIRD_SMS_ORIGINATOR'),
                ],
                'msg91' => [
                    'auth_key' => env('MSG91_AUTH_KEY'),
                    'template_id' => env('MSG91_TEMPLATE_ID'),
                    'sender' => env('MSG91_SENDER'),
                ],
                'custom' => [],
            ],
        ],

        'whatsapp' => [
            'enabled' => false,
            'provider' => env('LARAVEL_AUTH_WHATSAPP_PROVIDER', 'custom'),
            'view' => 'laravel-auth::messages.whatsapp',
            'custom_transport' => \GhostCompiler\LaravelAuth\OTP\Transport\CustomWhatsAppOtpTransport::class,
            'providers' => [
                'twilio' => [
                    'account_sid' => env('TWILIO_ACCOUNT_SID'),
                    'auth_token' => env('TWILIO_AUTH_TOKEN'),
                    'from' => env('TWILIO_WHATSAPP_FROM'),
                ],
                'custom' => [],
            ],
        ],
    ],

    'rate_limit' => [
        'otp_attempts' => 5,
        'passkey_attempts' => 5,
        'decay_seconds' => 60,
    ],

    'trusted_devices' => [
        'cookie' => 'laravel_auth_trusted_device',
        'ttl_days' => 30,
        'bind_user_agent' => true,
        'bind_ip' => false,
    ],

    'totp' => [
        'digits' => 6,
        'period' => 30,
        'window' => 1,
        'issuer' => env('APP_NAME', 'Laravel'),
    ],

    'recovery_codes' => [
        'count' => 8,
    ],

    'webauthn' => [
        'rp_name' => env('APP_NAME', 'Laravel'),
        'rp_id' => env('LARAVEL_AUTH_RP_ID'),
        'timeout' => 240,
        'user_verification' => 'required',
        'require_resident_key' => true,
        'allow_usb' => true,
        'allow_nfc' => true,
        'allow_ble' => true,
        'allow_hybrid' => true,
        'allow_internal' => true,
        'attestation_formats' => ['none'],
    ],

    'social' => [
        'default_stateless' => (bool) env('LARAVEL_AUTH_SOCIAL_STATELESS', false),

        'providers' => [
            'google' => [
                'enabled' => (bool) env('LARAVEL_AUTH_SOCIAL_GOOGLE_ENABLED', false),
                'label' => 'Google',
                'client_id' => env('GOOGLE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_CLIENT_SECRET'),
                'redirect' => env('GOOGLE_REDIRECT_URI'),
                'scopes' => ['openid', 'profile', 'email'],
                'with' => [],
            ],

            'github' => [
                'enabled' => (bool) env('LARAVEL_AUTH_SOCIAL_GITHUB_ENABLED', false),
                'label' => 'GitHub',
                'client_id' => env('GITHUB_CLIENT_ID'),
                'client_secret' => env('GITHUB_CLIENT_SECRET'),
                'redirect' => env('GITHUB_REDIRECT_URI'),
                'scopes' => ['read:user', 'user:email'],
                'with' => [],
            ],

            'facebook' => [
                'enabled' => (bool) env('LARAVEL_AUTH_SOCIAL_FACEBOOK_ENABLED', false),
                'label' => 'Facebook',
                'client_id' => env('FACEBOOK_CLIENT_ID'),
                'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
                'redirect' => env('FACEBOOK_REDIRECT_URI'),
                'scopes' => ['email'],
                'with' => [],
            ],

            'x' => [
                'enabled' => (bool) env('LARAVEL_AUTH_SOCIAL_X_ENABLED', false),
                'label' => 'X',
                'client_id' => env('X_CLIENT_ID'),
                'client_secret' => env('X_CLIENT_SECRET'),
                'redirect' => env('X_REDIRECT_URI'),
                'scopes' => [],
                'with' => [],
            ],

            'linkedin-openid' => [
                'enabled' => (bool) env('LARAVEL_AUTH_SOCIAL_LINKEDIN_OPENID_ENABLED', false),
                'label' => 'LinkedIn',
                'client_id' => env('LINKEDIN_OPENID_CLIENT_ID'),
                'client_secret' => env('LINKEDIN_OPENID_CLIENT_SECRET'),
                'redirect' => env('LINKEDIN_OPENID_REDIRECT_URI'),
                'scopes' => ['openid', 'profile', 'email'],
                'with' => [],
            ],

            'gitlab' => [
                'enabled' => (bool) env('LARAVEL_AUTH_SOCIAL_GITLAB_ENABLED', false),
                'label' => 'GitLab',
                'client_id' => env('GITLAB_CLIENT_ID'),
                'client_secret' => env('GITLAB_CLIENT_SECRET'),
                'redirect' => env('GITLAB_REDIRECT_URI'),
                'scopes' => ['read_user'],
                'with' => [],
            ],

            'bitbucket' => [
                'enabled' => (bool) env('LARAVEL_AUTH_SOCIAL_BITBUCKET_ENABLED', false),
                'label' => 'Bitbucket',
                'client_id' => env('BITBUCKET_CLIENT_ID'),
                'client_secret' => env('BITBUCKET_CLIENT_SECRET'),
                'redirect' => env('BITBUCKET_REDIRECT_URI'),
                'scopes' => ['account', 'email'],
                'with' => [],
            ],

            'slack' => [
                'enabled' => (bool) env('LARAVEL_AUTH_SOCIAL_SLACK_ENABLED', false),
                'label' => 'Slack',
                'client_id' => env('SLACK_CLIENT_ID'),
                'client_secret' => env('SLACK_CLIENT_SECRET'),
                'redirect' => env('SLACK_REDIRECT_URI'),
                'scopes' => [],
                'with' => [],
            ],

            'slack-openid' => [
                'enabled' => (bool) env('LARAVEL_AUTH_SOCIAL_SLACK_OPENID_ENABLED', false),
                'label' => 'Slack OpenID',
                'client_id' => env('SLACK_OPENID_CLIENT_ID'),
                'client_secret' => env('SLACK_OPENID_CLIENT_SECRET'),
                'redirect' => env('SLACK_OPENID_REDIRECT_URI'),
                'scopes' => ['openid', 'profile', 'email'],
                'with' => [],
            ],
        ],
    ],

    'presets' => [
        'strict' => [
            'enforce_2fa' => true,
            'proof_ttl_seconds' => 300,
            'otp_channels' => [
                'ttl_seconds' => 300,
                'max_attempts' => 5,
            ],
            'rate_limit' => [
                'otp_attempts' => 5,
                'passkey_attempts' => 5,
                'decay_seconds' => 60,
            ],
            'trusted_devices' => [
                'bind_user_agent' => true,
                'bind_ip' => true,
            ],
            'webauthn' => [
                'user_verification' => 'required',
            ],
        ],
    ],
];
