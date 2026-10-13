<?php

return [
    'intervals' => [300, 600, 900, 1800, 3600],
    
    'free' => [
        'max_monitors' => 3,
        'min_interval' => 900,
        'retention_days' => 3,
    ],

    'pro' => [
        'max_monitors' => 50,
        'min_interval' => 300,
        'retention_days' => 90,
        'stripe_price_monthly' => env('STRIPE_PRICE_PRO_MONTHLY'),
        'stripe_price_yearly' => env('STRIPE_PRICE_PRO_YEARLY'),
    ],
];
