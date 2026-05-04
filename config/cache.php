<?php

declare(strict_types=1);

return [
    'driver' => env('CACHE_DRIVER', 'redis'),
    'path' => env('CACHE_PATH', 'storage/cache'),
    'default_ttl' => (int) env('CACHE_TTL', 3600),
    'redis' => [
        'host' => env('CACHE_REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('CACHE_REDIS_PORT', 6379),
        'password' => env('CACHE_REDIS_PASSWORD', null),
        'database' => (int) env('CACHE_REDIS_DB', 0),
        'prefix' => env('CACHE_REDIS_PREFIX', 'launchpilot:cache:'),
    ],
];
