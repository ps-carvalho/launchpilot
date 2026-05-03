<?php

declare(strict_types=1);

return [
    'assetEntry' => env('INERTIA_REACT_CLIENT_ENTRY', 'js/app.jsx'),
    'ssr' => [
        'enabled' => env('INERTIA_SSR_ENABLED', false),
        'url' => env('INERTIA_SSR_URL', 'http://localhost:13714'),
    ],
];
