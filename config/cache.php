<?php

declare(strict_types=1);

return [
    'driver' => env('CACHE_DRIVER', 'file'),
    'path' => env('CACHE_PATH', 'storage/cache'),
    'default_ttl' => (int) env('CACHE_TTL', 3600),
];
