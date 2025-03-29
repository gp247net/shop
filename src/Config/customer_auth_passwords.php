<?php
return [
    'customer_password' => [
        'provider' => 'customer_provider',
        'table'    => config('gp247-config.env.GP247_DB_PREFIX').'shop_password_resets',
        'expire'   => 60,
    ],
];
