<?php

declare(strict_types=1);

namespace App\Dashboard\Context\Builder;

/**
 * Builds contextual information for an agent conversation.
 * Implementations may use vector search, raw documents, or external APIs.
 */
interface AgentContextBuilder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(string $message, int $workspaceId, int $userId): array;

    /**
     * Whether this builder supports the given agent type.
     */
    public function supports(string $agentType): bool;
}
