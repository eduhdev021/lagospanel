<?php

return [
    'outgoing_webhooks' => (bool) env('OUTGOING_WEBHOOKS_ENABLED', false),
    'native_provisioning' => (bool) env('NATIVE_PROVISIONING_ENABLED', false),
    'reservation_minutes' => max(15, min(10080, (int) env('ORDER_RESERVATION_MINUTES', 1440))),
    'version' => '1.7.0-dev',
    'payments' => [
        'live' => (bool) env('PAYMENTS_LIVE', false),
        'stripe' => [
            'enabled' => (bool) env('STRIPE_ENABLED', false),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        'mercadopago' => [
            'enabled' => (bool) env('MP_ENABLED', false),
            'token' => env('MP_ACCESS_TOKEN'),
        ],
        'efi' => [
            'enabled' => (bool) env('EFI_ENABLED', false),
            'environment' => env('EFI_ENVIRONMENT', 'homologacao'),
            'client_id' => env('EFI_CLIENT_ID'),
            'client_secret' => env('EFI_CLIENT_SECRET'),
            'certificate' => env('EFI_CERTIFICATE_PATH'),
            'certificate_password' => env('EFI_CERTIFICATE_PASSWORD'),
            'certificate_type' => env('EFI_CERTIFICATE_TYPE', 'PEM'),
            'pix_key' => env('EFI_PIX_KEY'),
            'webhook_hmac' => env('EFI_WEBHOOK_HMAC'),
            'charge_expiration' => max(300, min(86400, (int) env('EFI_CHARGE_EXPIRATION', 3600))),
        ],
    ],
];
