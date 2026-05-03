<?php

declare(strict_types=1);

namespace App\Web\Auth;

use App\Web\Entity\User;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;

class DatabaseUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly PasswordHasherInterface $hasher,
    ) {}

    public function retrieveById(int|string $identifier): ?AuthenticatableInterface
    {
        $row = $this->queryFactory->create()->table('users')->where('id', '=', (int) $identifier)->first();

        return $row ? $this->mapToUser($row) : null;
    }

    public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface
    {
        $query = $this->queryFactory->create()->table('users');

        if (isset($credentials['email'])) {
            $query->where('email', '=', $credentials['email']);
        }

        $row = $query->first();

        return $row ? $this->mapToUser($row) : null;
    }

    public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool
    {
        return $this->hasher->verify($credentials['password'] ?? '', $user->getAuthPassword());
    }

    public function retrieveByRememberToken(int|string $identifier, string $token): ?AuthenticatableInterface
    {
        return null;
    }

    public function updateRememberToken(AuthenticatableInterface $user, ?string $token): void
    {
        $this->queryFactory->create()->table('users')
            ->where('id', '=', $user->getAuthIdentifier())
            ->update(['remember_token' => $token]);
    }

    private function mapToUser(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            name: $row['name'],
            email: $row['email'],
            password: $row['password'],
            remember_token: $row['remember_token'] ?? null,
        );
    }
}
