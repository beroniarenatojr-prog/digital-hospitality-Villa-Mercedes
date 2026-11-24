<?php

return [
    'deposit_workflow' => (bool) env('PAYMENT_DEPOSIT_WORKFLOW', true),
    'allow_gateway_checkout' => false,

    /*
    | How long a new online hold waits for its deposit before the scheduler
    | releases it (see DepositDeadline and `reservations:expire-unpaid`), and how
    | long a submitted proof may sit before the review screen calls it overdue.
    */
    'hold_hours' => 24,
    'review_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Online payment gateway
    |--------------------------------------------------------------------------
    |
    | New checkout is disabled. Keep gateway configuration only to reconcile
    | existing historical checkout records. New deposits use administrator-
    | verified QR receiving accounts and manually verified transfer proofs.
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

    /*
    | Manual transfer details shown on the guest payment page. Keep these
    | aligned with the accounts staff actually verify.
    */
    'recipients' => [
        [
            'method' => 'GCash',
            'account_name' => env('PAYMENTS_GCASH_NAME', 'Villa Mercedes Nature Farm & Resort'),
            'account_number' => env('PAYMENTS_GCASH_NUMBER'),
        ],
        [
            'method' => 'Maya',
            'account_name' => env('PAYMENTS_MAYA_NAME', 'Villa Mercedes Nature Farm & Resort'),
            'account_number' => env('PAYMENTS_MAYA_NUMBER'),
        ],
        [
            'method' => 'Bank transfer',
            'account_name' => env('PAYMENTS_BANK_NAME', 'Villa Mercedes Nature Farm & Resort'),
            'account_number' => env('PAYMENTS_BANK_NUMBER'),
            'bank' => env('PAYMENTS_BANK_LABEL'),
        ],
    ],

];
