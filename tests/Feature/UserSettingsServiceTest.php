<?php

declare(strict_types=1);

use App\Dashboard\Service\UserSettingsService;

beforeEach(function () {
    $this->service = $this->container()->get(UserSettingsService::class);
});

describe('getOrCreate', function () {
    it('creates default settings for new user', function () {
        $userId = $this->createUser();

        $settings = $this->service->getOrCreate($userId);

        expect($settings['tier'])->toBe('free')
            ->and((int) $settings['daily_runs_used'])->toBe(0)
            ->and($settings['runs_reset_at'])->not->toBeNull();
    });

    it('returns existing settings without overwriting', function () {
        $userId = $this->createUser();

        $first = $this->service->getOrCreate($userId);
        $second = $this->service->getOrCreate($userId);

        expect($second['id'])->toBe($first['id']);
    });

    it('resets daily runs when date has changed', function () {
        $userId = $this->createUser();

        // Create settings with yesterday's reset date
        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'free',
            'daily_runs_used' => 5,
            'runs_reset_at' => gmdate('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $settings = $this->service->getOrCreate($userId);

        expect((int) $settings['daily_runs_used'])->toBe(0);
    });

    it('preserves daily runs on same day', function () {
        $userId = $this->createUser();

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'free',
            'daily_runs_used' => 7,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $settings = $this->service->getOrCreate($userId);

        expect((int) $settings['daily_runs_used'])->toBe(7);
    });
});

describe('canRunAgent', function () {
    it('allows free user under limit', function () {
        $userId = $this->createUser();

        expect($this->service->canRunAgent($userId))->toBeTrue();
    });

    it('blocks free user at limit', function () {
        $userId = $this->createUser();

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'free',
            'daily_runs_used' => 10,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        expect($this->service->canRunAgent($userId))->toBeFalse();
    });

    it('allows pro user above free limit', function () {
        $userId = $this->createUser();

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'pro',
            'daily_runs_used' => 999,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        expect($this->service->canRunAgent($userId))->toBeTrue();
    });
});

describe('incrementRunCount', function () {
    it('increments run count for free user', function () {
        $userId = $this->createUser();

        $this->service->incrementRunCount($userId);
        $settings = $this->service->getOrCreate($userId);

        expect((int) $settings['daily_runs_used'])->toBe(1);
    });

    it('throws when free user exceeds limit', function () {
        $userId = $this->createUser();

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'free',
            'daily_runs_used' => 10,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        expect(fn () => $this->service->incrementRunCount($userId))
            ->toThrow(\RuntimeException::class, 'Rate limit exceeded.');
    });

    it('allows pro user to exceed free limit', function () {
        $userId = $this->createUser();

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'pro',
            'daily_runs_used' => 10,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->service->incrementRunCount($userId);
        $settings = $this->service->getOrCreate($userId);

        expect((int) $settings['daily_runs_used'])->toBe(11);
    });
});

describe('getRemainingRuns', function () {
    it('returns 10 for fresh free user', function () {
        $userId = $this->createUser();

        expect($this->service->getRemainingRuns($userId))->toBe(10);
    });

    it('returns correct remaining for used runs', function () {
        $userId = $this->createUser();

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'free',
            'daily_runs_used' => 3,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        expect($this->service->getRemainingRuns($userId))->toBe(7);
    });

    it('returns -1 for pro user', function () {
        $userId = $this->createUser();

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'pro',
            'daily_runs_used' => 0,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        expect($this->service->getRemainingRuns($userId))->toBe(-1);
    });

    it('returns 0 when over limit', function () {
        $userId = $this->createUser();

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'free',
            'daily_runs_used' => 15,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        expect($this->service->getRemainingRuns($userId))->toBe(0);
    });
});

describe('getEffectiveApiKey', function () {
    it('returns env key for free user', function () {
        $userId = $this->createUser();
        $_ENV['OPENROUTER_API_KEY'] = 'env-key-123';

        expect($this->service->getEffectiveApiKey($userId))->toBe('env-key-123');
    });

    it('returns user BYOK for pro user with custom key', function () {
        $userId = $this->createUser();

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'pro',
            'openrouter_api_key' => 'user-custom-key',
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        expect($this->service->getEffectiveApiKey($userId))->toBe('user-custom-key');
    });

    it('falls back to env key when pro user has no custom key', function () {
        $userId = $this->createUser();
        $_ENV['OPENROUTER_API_KEY'] = 'fallback-key';

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'pro',
            'openrouter_api_key' => '',
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        expect($this->service->getEffectiveApiKey($userId))->toBe('fallback-key');
    });
});

describe('custom prompts', function () {
    it('stores and retrieves custom prompts', function () {
        $userId = $this->createUser();
        $this->service->getOrCreate($userId);

        $prompts = ['social' => 'Custom social prompt'];
        $this->service->updateCustomPrompts($userId, $prompts);

        expect($this->service->getCustomPrompts($userId))->toBe($prompts);
    });

    it('returns empty array when no custom prompts set', function () {
        $userId = $this->createUser();

        expect($this->service->getCustomPrompts($userId))->toBe([]);
    });
});
