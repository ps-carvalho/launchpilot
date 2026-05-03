<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

/**
 * Backward-compatible facade over the split settings modules.
 * New code should inject UsageQuota, ApiKeyResolver, or AgentPromptRegistry directly.
 *
 * @deprecated Use UsageQuota, ApiKeyResolver, or AgentPromptRegistry instead.
 */
class UserSettingsService
{
    public function __construct(
        private readonly UsageQuota $usageQuota,
        private readonly ApiKeyResolver $apiKeyResolver,
        private readonly AgentPromptRegistry $promptRegistry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getOrCreate(int $userId): array
    {
        return $this->usageQuota->getOrCreate($userId);
    }

    public function incrementRunCount(int $userId): void
    {
        $this->usageQuota->recordRun($userId);
    }

    public function canRunAgent(int $userId): bool
    {
        return $this->usageQuota->canRun($userId);
    }

    public function getRemainingRuns(int $userId): int
    {
        return $this->usageQuota->remaining($userId);
    }

    public function getEffectiveApiKey(int $userId): string
    {
        return $this->apiKeyResolver->resolve($userId);
    }

    public function updateCustomPrompts(int $userId, array $prompts): void
    {
        $this->promptRegistry->set($userId, $prompts);
    }

    /**
     * @return array<string, string>
     */
    public function getCustomPrompts(int $userId): array
    {
        return $this->promptRegistry->all($userId);
    }
}
