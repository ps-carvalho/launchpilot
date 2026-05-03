<?php

declare(strict_types=1);

return [
    'enabled' => env('DEBUGBAR_ENABLED', env('APP_DEBUG', false)),
    'inject' => env('DEBUGBAR_INJECT', true),
    'capture_cli' => env('DEBUGBAR_CAPTURE_CLI', false),
    'theme' => env('DEBUGBAR_THEME', 'auto'),
    'route' => [
        'open' => env('DEBUGBAR_ROUTE_OPEN', false),
        'allowed_ips' => ['127.0.0.1', '::1'],
    ],
    'storage' => [
        'path' => 'storage/debugbar',
        'max_files' => 100,
    ],
    'collectors' => [
        'request' => true,
        'response' => true,
        'time' => true,
        'memory' => true,
        'messages' => true,
        'database' => true,
        'logs' => true,
        'views' => true,
        'config' => false,
        'inertia' => true,
    ],
    'options' => [
        'database' => [
            'slow_threshold_ms' => 100,
        ],
    ],
];
