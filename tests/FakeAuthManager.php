<?php

declare(strict_types=1);

namespace Tests;

use Marko\Authentication\AuthManager;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;

class FakeAuthManager extends AuthManager
{
    private ?int $fakeUserId = null;

    public function __construct()
    {
        // Skip parent constructor - we don't need real deps
    }

    public function setUserId(int $userId): void
    {
        $this->fakeUserId = $userId;
    }

    public function guard(?string $name = null): GuardInterface
    {
        return new FakeGuard($this->fakeUserId);
    }

    public function id(): int|string|null
    {
        return $this->fakeUserId;
    }

    public function check(): bool
    {
        return $this->fakeUserId !== null;
    }

    public function guest(): bool
    {
        return $this->fakeUserId === null;
    }
}
