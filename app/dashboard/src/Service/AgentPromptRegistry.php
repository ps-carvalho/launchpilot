<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use Marko\Database\Query\QueryBuilderFactoryInterface;

/**
 * CRUD for per-user custom agent prompts.
 */
class AgentPromptRegistry
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    public function get(int $userId, string $agentType): ?string
    {
        $prompts = $this->all($userId);
        return $prompts[$agentType] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(int $userId): array
    {
        $settings = $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();

        return json_decode($settings['custom_prompts'] ?? '{}', true) ?: [];
    }

    /**
     * @param array<string, string> $prompts
     */
    public function set(int $userId, array $prompts): void
    {
        $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->update([
                'custom_prompts' => json_encode($prompts),
            ]);
    }
}
