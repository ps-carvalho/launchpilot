<?php

declare(strict_types=1);

return [
    'driver' => env('LOG_DRIVER', 'file'),
    'path' => (static function (): string {
        $path = env('LOG_PATH', 'storage/logs');
        if (str_starts_with($path, '/')) {
            return $path;
        }
        return dirname(__DIR__) . '/' . $path;
    })(),
    'level' => env('LOG_LEVEL', 'debug'),
    'channel' => env('LOG_CHANNEL', 'launchpilot'),
    'format' => '[{datetime}] {channel}.{level}: {message} {context}',
    'date_format' => 'Y-m-d H:i:s',
    'max_files' => (int) env('LOG_MAX_FILES', 30),
    'max_file_size' => (int) env('LOG_MAX_FILE_SIZE', 10 * 1024 * 1024),
];
