<?php

return [
    'timezone' => 'Africa/Lagos',
    'admin_email' => env('RESTAURANT_ADMIN_EMAIL', ''),
    'send_mail' => env('RESTAURANT_SEND_MAIL', false),
    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'api_url' => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),
        'webhook_secrets' => array_filter(explode(',', (string) env('PAYSTACK_WEBHOOK_SECRET', ''))),
    ],
];

