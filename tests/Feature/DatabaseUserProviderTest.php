<?php

declare(strict_types=1);

use App\Web\Auth\DatabaseUserProvider;

beforeEach(function () {
    $this->provider = $this->container()->get(DatabaseUserProvider::class);
});

describe('retrieveById', function () {
    it('returns user when id exists', function () {
        $userId = $this->createUser('John', 'john@example.com');

        $user = $this->provider->retrieveById($userId);

        expect($user)->not->toBeNull()
            ->and($user->getAuthIdentifier())->toBe($userId)
            ->and($user->name)->toBe('John')
            ->and($user->email)->toBe('john@example.com');
    });

    it('returns null for non-existent id', function () {
        expect($this->provider->retrieveById(99999))->toBeNull();
    });
});

describe('retrieveByCredentials', function () {
    it('finds user by email', function () {
        $this->createUser('Jane', 'jane@example.com');

        $user = $this->provider->retrieveByCredentials(['email' => 'jane@example.com']);

        expect($user)->not->toBeNull()
            ->and($user->email)->toBe('jane@example.com');
    });

    it('returns null for unknown email', function () {
        expect($this->provider->retrieveByCredentials(['email' => 'nobody@example.com']))->toBeNull();
    });

    it('returns first user when no email filter', function () {
        $this->createUser('First', 'first@example.com');
        $this->createUser('Second', 'second@example.com');

        $user = $this->provider->retrieveByCredentials([]);

        expect($user)->not->toBeNull();
    });
});

describe('validateCredentials', function () {
    it('returns true for correct password', function () {
        $userId = $this->createUser('Test', 'test@example.com', 'secret123');
        $user = $this->provider->retrieveById($userId);

        expect($this->provider->validateCredentials($user, ['password' => 'secret123']))->toBeTrue();
    });

    it('returns false for wrong password', function () {
        $userId = $this->createUser('Test', 'test@example.com', 'secret123');
        $user = $this->provider->retrieveById($userId);

        expect($this->provider->validateCredentials($user, ['password' => 'wrong']))->toBeFalse();
    });

    it('returns false for empty password', function () {
        $userId = $this->createUser('Test', 'test@example.com', 'secret123');
        $user = $this->provider->retrieveById($userId);

        expect($this->provider->validateCredentials($user, ['password' => '']))->toBeFalse();
    });

    it('returns false when password key missing', function () {
        $userId = $this->createUser('Test', 'test@example.com', 'secret123');
        $user = $this->provider->retrieveById($userId);

        expect($this->provider->validateCredentials($user, []))->toBeFalse();
    });
});

describe('remember token', function () {
    it('returns null for retrieveByRememberToken', function () {
        expect($this->provider->retrieveByRememberToken(1, 'token'))->toBeNull();
    });

    it('updates remember token', function () {
        $userId = $this->createUser('Test', 'test@example.com');
        $user = $this->provider->retrieveById($userId);

        $this->provider->updateRememberToken($user, 'new-token-123');

        $updated = $this->provider->retrieveById($userId);
        expect($updated->getRememberToken())->toBe('new-token-123');
    });

    it('can set remember token to null', function () {
        $userId = $this->createUser('Test', 'test@example.com');
        $user = $this->provider->retrieveById($userId);

        $this->provider->updateRememberToken($user, 'token');
        $this->provider->updateRememberToken($user, null);

        $updated = $this->provider->retrieveById($userId);
        expect($updated->getRememberToken())->toBeNull();
    });
});
