<?php

declare(strict_types=1);

use App\User\Provider\DatabaseUserProvider;
use Marko\Authentication\Contracts\UserProviderInterface;

return [
    'enabled' => true,
    'bindings' => [
        UserProviderInterface::class => DatabaseUserProvider::class,
    ],
];
