<?php

declare(strict_types=1);

namespace App\Dashboard\Gate;

use App\Dashboard\Authorization\WorkspaceAuthorization;
use Marko\Database\Query\QueryBuilderFactoryInterface;

/**
 * Centralizes knowledge base document ownership queries.
 */
class KnowledgeBaseGate
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly WorkspaceAuthorization $workspaceAuth,
    ) {}

    public function documentForUser(int $userId, int $documentId): ?array
    {
        return $this->workspaceAuth->documentFor($userId, $documentId);
    }

    public function firstWorkspaceForUser(int $userId): ?array
    {
        return $this->workspaceAuth->firstWorkspaceFor($userId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function documentsForUser(int $userId): array
    {
        $workspaceIds = $this->workspaceAuth->workspaceIdsFor($userId);

        if (empty($workspaceIds)) {
            return [];
        }

        return $this->queryFactory->create()->table('knowledge_documents')
            ->whereIn('workspace_id', $workspaceIds)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
}
