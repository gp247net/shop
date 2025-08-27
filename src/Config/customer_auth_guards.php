<?php
return [
    'customer' => [
        'driver'   => 'session',
        'provider' => 'customer_provider',
    ],
    'customer-api' => [
        'driver'   => 'sanctum',
        'provider' => 'customer_provider',
    ],
];
