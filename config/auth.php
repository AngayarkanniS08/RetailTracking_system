<?php
declare(strict_types=1);

return [
    'cookie_name'    => 'auth_uid',
    'token_key'      => 'auth_token',
    'session_ttl'    => 86400 * 7, // 7 days
    'guest_routes'   => ['/login', '/register', '/forgot-password', '/reset-password'],
    'roles'          => [
        'admin'   => ['*'],
        'cashier' => ['/dashboard', '/billing', '/daily-sales', '/products'],
        'manager' => ['/dashboard', '/billing', '/daily-sales', '/products', '/inventory', '/vendors', '/vendors/history', '/customers', '/reports'],
    ],
];
