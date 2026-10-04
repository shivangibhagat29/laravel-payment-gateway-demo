<?php

return [
    // Public key id — safe to expose to the browser (used by Razorpay Checkout).
    'key' => env('RAZORPAY_KEY'),

    // Secret key — server side only. Used for API auth and payment signature checks.
    'secret' => env('RAZORPAY_SECRET'),

    // Separate secret configured in the Razorpay dashboard for webhooks.
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),

    'base_url' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com/v1'),

    'currency' => env('RAZORPAY_CURRENCY', 'INR'),
];
