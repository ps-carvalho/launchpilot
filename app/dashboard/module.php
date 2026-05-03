<?php

declare(strict_types=1);

use App\Dashboard\Http\HttpClientInterface;
use App\Dashboard\Http\StreamHttpClient;

return [
    'enabled' => true,
    'bindings' => [
        HttpClientInterface::class => StreamHttpClient::class,
    ],
];
