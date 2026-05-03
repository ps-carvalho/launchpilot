<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use Marko\Database\Query\QueryBuilderFactoryInterface;

/**
 * Tracks daily agent run quotas and tier-based limits.
 * Extracted from UserSettingsService to isolate usage accounting.
 */
class UsageQuota
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    public function canRun(int $userId): bool
    {
        $settings = $this->getOrCreate($userId);

        if ($settings['tier'] === 'pro') {
            return true;
        }

        return (int) $settings['daily_runs_used'] < 10;
    }

    public function remaining(int $userId): int
    {
        $settings = $this->getOrCreate($userId);

        if ($settings['tier'] === 'pro') {
            return -1;
        }

        return max(0, 10 - (int) $settings['daily_runs_used']);
    }

    public function recordRun(int $userId): void
    {
        $settings = $this->getOrCreate($userId);

        if ($settings['tier'] !== 'pro' && (int) $settings['daily_runs_used'] >= 10) {
            throw new \RuntimeException('Rate limit exceeded.');
        }

        $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->update([
                'daily_runs_used' => (int) $settings['daily_runs_used'] + 1,
            ]);
    }

    public function dailyRunsUsed(int $userId): int
    {
        $settings = $this->getOrCreate($userId);
        return (int) $settings['daily_runs_used'];
    }

    public function tier(int $userId): string
    {
        $settings = $this->getOrCreate($userId);
        return $settings['tier'];
    }

    /**
     * @return array<string, mixed>
     */
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
                'runs_reset_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $settings = $this->queryFactory->create()->table('user_settings')
                ->where('user_id', '=', $userId)
                ->first();
        }

        $resetAt = $settings['runs_reset_at'] ?? null;
        if ($resetAt === null || gmdate('Y-m-d', strtotime($resetAt . ' UTC')) !== gmdate('Y-m-d')) {
            $this->queryFactory->create()->table('user_settings')
                ->where('user_id', '=', $userId)
                ->update([
                    'daily_runs_used' => 0,
                    'runs_reset_at' => gmdate('Y-m-d H:i:s'),
                ]);
            $settings['daily_runs_used'] = 0;
        }

        return $settings;
    }
}
