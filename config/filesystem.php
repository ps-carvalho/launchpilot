<?php

declare(strict_types=1);

return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'path' => 'storage',
            'public' => false,
        ],
        'media' => [
            'driver' => 'local',
            'path' => 'storage/media',
            'public' => true,
            'url' => '/media',
        ],
        'temp' => [
            'driver' => 'local',
            'path' => 'storage/temp',
            'public' => false,
        ],
    ],
];
