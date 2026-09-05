<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Online payment gateway
    |--------------------------------------------------------------------------
    |
    | 'manual' keeps the resort on proof-of-payment uploads only, which is how
    | the property has always taken GCash and Maya. Setting 'paymongo' with keys
    | adds live e-wallet checkout on top; proof upload always stays available as
    | the fallback, because provincial guests still walk in with receipts.
    |
    */
    'gateway' => env('PAYMENT_GATEWAY', 'manual'),

    'currency' => 'PHP',

    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'public_key' => env('PAYMONGO_PUBLIC_KEY'),
        'base_url' => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v1'),
        'payment_methods' => ['gcash', 'paymaya', 'card'],
    ],

];
