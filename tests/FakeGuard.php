<?php

declare(strict_types=1);

namespace Tests;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;

class FakeGuard implements GuardInterface
{
    public function __construct(private ?int $userId) {}

    public function check(): bool
    {
        return $this->userId !== null;
    }

    public function guest(): bool
    {
        return $this->userId === null;
    }

    public function user(): ?AuthenticatableInterface
    {
        return null; // Not needed for these tests
    }

    public function id(): int|string|null
    {
        return $this->userId;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        return false;
    }

    public function login(AuthenticatableInterface $user, bool $remember = false): void
    {
    }

    public function logout(): void
    {
        $this->userId = null;
    }
}
