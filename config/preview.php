<?php

return [
    'enabled' => (bool) env('PREVIEW_MODE', false),
    'public_interface' => (bool) env('PREVIEW_PUBLIC_INTERFACE', false),
    'guest' => [
        'email' => env('PREVIEW_GUEST_EMAIL', 'customer@villamercedes.test'),
        'password' => env('PREVIEW_GUEST_PASSWORD', 'Password123!'),
    ],
    'admin' => [
        'email' => env('PREVIEW_ADMIN_EMAIL', 'admin@villamercedes.test'),
        'password' => env('PREVIEW_ADMIN_PASSWORD'),
    ],
];
