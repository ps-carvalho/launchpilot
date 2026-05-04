<?php

declare(strict_types=1);

use Marko\Queue\Worker;
use Marko\Queue\WorkerInterface;

return [
    'enabled' => true,
    'bindings' => [
        WorkerInterface::class => Worker::class,
    ],
];
