<?php

return ['outgoing_webhooks' => (bool) env('OUTGOING_WEBHOOKS_ENABLED', false), 'native_provisioning' => (bool) env('NATIVE_PROVISIONING_ENABLED', false), 'reservation_minutes' => max(15, min(10080, (int) env('ORDER_RESERVATION_MINUTES', 1440))), 'version' => '1.5.0-dev', 'payments' => ['live' => (bool) env('PAYMENTS_LIVE', false), 'stripe' => ['enabled' => (bool) env('STRIPE_ENABLED', false), 'secret' => env('STRIPE_SECRET'), 'webhook_secret' => env('STRIPE_WEBHOOK_SECRET')], 'mercadopago' => ['enabled' => (bool) env('MP_ENABLED', false), 'token' => env('MP_ACCESS_TOKEN')]]];
