<?php

declare(strict_types=1);

use App\Dashboard\Context\Builder\ContextBuilderRegistry;
use App\Dashboard\Context\Builder\GscContextBuilder;
use App\Dashboard\Context\Builder\KnowledgeBaseContextBuilder;
use App\Dashboard\Http\HttpClientInterface;
use App\Dashboard\Http\StreamHttpClient;
use Marko\Core\Container\ContainerInterface;

return [
    'enabled' => true,
    'bindings' => [
        HttpClientInterface::class => StreamHttpClient::class,
        ContextBuilderRegistry::class => function (ContainerInterface $container): ContextBuilderRegistry {
            $registry = new ContextBuilderRegistry();
            $registry->register($container->get(KnowledgeBaseContextBuilder::class));
            $registry->register($container->get(GscContextBuilder::class));
            return $registry;
        },
    ],
];
