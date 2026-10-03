<?php

return ['native_provisioning' => (bool) env('NATIVE_PROVISIONING_ENABLED', false), 'reservation_minutes' => max(15, min(10080, (int) env('ORDER_RESERVATION_MINUTES', 1440))), 'version' => '1.0.0', 'payments' => ['live' => (bool) env('PAYMENTS_LIVE', false), 'stripe' => ['enabled' => (bool) env('STRIPE_ENABLED', false), 'secret' => env('STRIPE_SECRET'), 'webhook_secret' => env('STRIPE_WEBHOOK_SECRET')], 'mercadopago' => ['enabled' => (bool) env('MP_ENABLED', false), 'token' => env('MP_ACCESS_TOKEN')]]];
