<?php

declare(strict_types=1);

namespace App\User\Entity;

use Marko\Authentication\AuthenticatableInterface;

class User implements AuthenticatableInterface
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $password,
        public ?string $remember_token = null,
    ) {}

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->remember_token;
    }

    public function setRememberToken(?string $token): void
    {
        $this->remember_token = $token;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
