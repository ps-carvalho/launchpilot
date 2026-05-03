<?php

declare(strict_types=1);

return [
    'driver' => env('DB_CONNECTION', 'pgsql'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 5432),
    'database' => env('DB_DATABASE', 'launchpilot'),
    'username' => env('DB_USERNAME', 'postgres'),
    'password' => env('DB_PASSWORD', ''),
];
