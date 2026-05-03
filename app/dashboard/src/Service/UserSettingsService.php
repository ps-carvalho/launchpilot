<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use Marko\Database\Query\QueryBuilderFactoryInterface;

class UserSettingsService
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    public function getOrCreate(int $userId): array
    {
        $settings = $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();

        if ($settings === null) {
            $this->queryFactory->create()->table('user_settings')->insert([
                'user_id' => $userId,
                'tier' => 'free',
                'daily_runs_used' => 0,
                'runs_reset_at' => date('Y-m-d H:i:s'),
            ]);

            $settings = $this->queryFactory->create()->table('user_settings')
                ->where('user_id', '=', $userId)
                ->first();
        }

        // Reset counter if it's a new day (UTC)
        $resetAt = $settings['runs_reset_at'] ?? null;
        if ($resetAt === null || date('Y-m-d', strtotime($resetAt)) !== date('Y-m-d')) {
            $this->queryFactory->create()->table('user_settings')
                ->where('user_id', '=', $userId)
                ->update([
                    'daily_runs_used' => 0,
                    'runs_reset_at' => date('Y-m-d H:i:s'),
                ]);
            $settings['daily_runs_used'] = 0;
        }

        return $settings;
    }

    public function incrementRunCount(int $userId): void
    {
        $settings = $this->getOrCreate($userId);
        $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->update([
                'daily_runs_used' => (int) $settings['daily_runs_used'] + 1,
            ]);
    }

    public function canRunAgent(int $userId): bool
    {
        $settings = $this->getOrCreate($userId);

        if ($settings['tier'] === 'pro') {
            return true;
        }

        return (int) $settings['daily_runs_used'] < 10;
    }

    public function getRemainingRuns(int $userId): int
    {
        $settings = $this->getOrCreate($userId);

        if ($settings['tier'] === 'pro') {
            return -1; // unlimited
        }

        return max(0, 10 - (int) $settings['daily_runs_used']);
    }

    public function getEffectiveApiKey(int $userId): string
    {
        $settings = $this->getOrCreate($userId);

        // Pro users with BYOK
        if ($settings['tier'] === 'pro' && !empty($settings['openrouter_api_key'])) {
            return $settings['openrouter_api_key'];
        }

        return $_ENV['OPENROUTER_API_KEY'] ?? '';
    }

    public function updateCustomPrompts(int $userId, array $prompts): void
    {
        $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->update([
                'custom_prompts' => json_encode($prompts),
            ]);
    }

    public function getCustomPrompts(int $userId): array
    {
        $settings = $this->getOrCreate($userId);
        return json_decode($settings['custom_prompts'] ?? '{}', true);
    }
}
