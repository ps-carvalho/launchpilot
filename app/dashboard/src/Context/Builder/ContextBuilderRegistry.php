<?php

declare(strict_types=1);

namespace App\Dashboard\Context\Builder;

/**
 * Registry of context builders keyed by agent type.
 * The pipeline asks the registry for the builder(s) relevant to the current agent.
 */
class ContextBuilderRegistry
{
    /** @var array<int, AgentContextBuilder> */
    private array $builders = [];

    public function register(AgentContextBuilder $builder): void
    {
        $this->builders[] = $builder;
    }

    /**
     * Collect context from all builders that support the given agent type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(string $agentType, string $message, int $workspaceId, int $userId): array
    {
        $context = [];

        foreach ($this->builders as $builder) {
            if ($builder->supports($agentType)) {
                $context = array_merge($context, $builder->build($message, $workspaceId, $userId));
            }
        }

        return $context;
    }
}
