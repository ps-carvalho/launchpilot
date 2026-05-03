<?php

declare(strict_types=1);

return [
    'entry' => env('VITE_ENTRY', 'js/app.jsx'),
    'buildDirectory' => 'build',
    'manifestFilename' => '.vite/manifest.json',
    'devServerUrl' => env('VITE_DEV_SERVER_URL', 'http://localhost:5173'),
    'devServerStylesheets' => [],
    'useDevServer' => env('VITE_USE_DEV_SERVER', env('APP_ENV', 'local') === 'local'),
];
