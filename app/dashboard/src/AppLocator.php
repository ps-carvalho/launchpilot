<?php

declare(strict_types=1);

namespace App\Dashboard;

use Marko\Core\Container\ContainerInterface;

/**
 * Static container locator for queue jobs and other contexts
 * where dependency injection is not available.
 */
final class AppLocator
{
    private static ?ContainerInterface $container = null;

    public static function set(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function get(string $id): mixed
    {
        if (self::$container === null) {
            throw new \RuntimeException('AppLocator container not set. Ensure the application is booted.');
        }

        return self::$container->get($id);
    }
}
