<?php
return [
    'customer' => [
        'driver'   => 'session',
        'provider' => 'customer_provider',
    ],
    'api' => [
        'driver'   => 'sanctum',
        'provider' => 'users',
    ],
    'customer-api' => [
        'driver'   => 'sanctum',
        'provider' => 'customer_provider',
    ],
];
