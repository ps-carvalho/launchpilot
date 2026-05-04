<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Log\Contracts\LoggerInterface;

/**
 * Resolves the effective OpenRouter API key for a user.
 * Pro users with BYOK override the default system key.
 */
class ApiKeyResolver
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function resolve(int $userId): string
    {
        $settings = $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();

        if ($settings !== null && $settings['tier'] === 'pro' && !empty($settings['openrouter_api_key'])) {
            $this->logger->debug('API key resolved from BYOK', ['user_id' => $userId]);
            return $settings['openrouter_api_key'];
        }

        $envKey = getenv('OPENROUTER_API_KEY') ?: '';

        if (empty($envKey)) {
            $this->logger->warning('No API key available', ['user_id' => $userId]);
        } else {
            $this->logger->debug('API key resolved from env', ['user_id' => $userId]);
        }

        return $envKey;
    }

    public function hasCustomKey(int $userId): bool
    {
        $settings = $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();

        return $settings !== null && $settings['tier'] === 'pro' && !empty($settings['openrouter_api_key']);
    }
}
