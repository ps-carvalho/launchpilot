<?php

declare(strict_types=1);

use App\Web\Auth\DatabaseUserProvider;
use Marko\Authentication\Contracts\UserProviderInterface;

return [
    'enabled' => true,
    'bindings' => [
        UserProviderInterface::class => DatabaseUserProvider::class,
    ],
];
