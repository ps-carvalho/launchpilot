<?php

declare(strict_types=1);

use Marko\Cache\Redis\RedisConnection;
use Marko\Core\Container\ContainerInterface;
use Marko\Queue\Worker;
use Marko\Queue\WorkerInterface;

return [
    'enabled' => true,
    'bindings' => [
        WorkerInterface::class => Worker::class,
        RedisConnection::class => function (ContainerInterface $container): RedisConnection {
            $config = $container->get(Marko\Config\ConfigRepositoryInterface::class);
            $redis = $config->getArray('cache.redis');

            return new RedisConnection(
                host: $redis['host'] ?? '127.0.0.1',
                port: (int) ($redis['port'] ?? 6379),
                password: $redis['password'] ?? null,
                database: (int) ($redis['database'] ?? 0),
                prefix: $redis['prefix'] ?? 'launchpilot:cache:',
            );
        },
    ],
];
