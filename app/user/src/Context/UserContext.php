<?php

declare(strict_types=1);

namespace App\User\Context;

use Marko\Authentication\AuthManager;

/**
 * Request-scoped context providing the authenticated user's ID.
 * Replaces the repeated `$this->auth->id() ?? 0` pattern in controllers.
 */
class UserContext
{
    public function __construct(
        private readonly AuthManager $auth,
    ) {}

    /**
     * Get the current user's ID. Returns 0 when unauthenticated,
     * matching the existing controller convention.
     */
    public function id(): int
    {
        $id = $this->auth->id();

        return $id !== null ? (int) $id : 0;
    }

    public function check(): bool
    {
        return $this->auth->id() !== null;
    }

    public function logout(): void
    {
        $this->auth->logout();
    }
}
