<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use Marko\Database\Query\QueryBuilderFactoryInterface;

/**
 * Resolves the effective OpenRouter API key for a user.
 * Pro users with BYOK override the default system key.
 */
class ApiKeyResolver
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    public function resolve(int $userId): string
    {
        $settings = $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();

        if ($settings !== null && $settings['tier'] === 'pro' && !empty($settings['openrouter_api_key'])) {
            return $settings['openrouter_api_key'];
        }

        return $_ENV['OPENROUTER_API_KEY'] ?? '';
    }

    public function hasCustomKey(int $userId): bool
    {
        $settings = $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();

        return $settings !== null && $settings['tier'] === 'pro' && !empty($settings['openrouter_api_key']);
    }
}
