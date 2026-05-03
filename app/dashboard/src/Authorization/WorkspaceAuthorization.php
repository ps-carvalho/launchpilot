<?php

declare(strict_types=1);

namespace App\Dashboard\Authorization;

use Marko\Database\Query\QueryBuilderFactoryInterface;

class WorkspaceAuthorization
{
    private ?array $workspaceIds = null;

    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    /**
     * Get all workspace IDs the user belongs to.
     *
     * @return array<int>
     */
    public function workspaceIdsFor(int $userId): array
    {
        if ($this->workspaceIds !== null) {
            return $this->workspaceIds;
        }

        $rows = $this->queryFactory->create()->table('workspace_user')
            ->select('workspace_id')
            ->where('user_id', '=', $userId)
            ->get();

        $this->workspaceIds = array_column($rows, 'workspace_id');

        return $this->workspaceIds;
    }

    /**
     * Clear cached workspace IDs (useful between requests).
     */
    public function clearCache(): void
    {
        $this->workspaceIds = null;
    }

    /**
     * Assert that a campaign belongs to one of the user's workspaces.
     * Returns the campaign row or null.
     */
    public function campaignFor(int $userId, int $campaignId): ?array
    {
        $ids = $this->workspaceIdsFor($userId);

        if (empty($ids)) {
            return null;
        }

        return $this->queryFactory->create()->table('campaigns')
            ->where('id', '=', $campaignId)
            ->whereIn('workspace_id', $ids)
            ->first();
    }

    /**
     * Assert that a document belongs to one of the user's workspaces.
     * Returns the document row or null.
     */
    public function documentFor(int $userId, int $documentId): ?array
    {
        $ids = $this->workspaceIdsFor($userId);

        if (empty($ids)) {
            return null;
        }

        return $this->queryFactory->create()->table('knowledge_documents')
            ->where('id', '=', $documentId)
            ->whereIn('workspace_id', $ids)
            ->first();
    }

    /**
     * Assert that a content item belongs to one of the user's workspaces
     * via its campaign. Returns the item row or null.
     */
    public function contentItemFor(int $userId, int $itemId): ?array
    {
        $ids = $this->workspaceIdsFor($userId);

        if (empty($ids)) {
            return null;
        }

        $item = $this->queryFactory->create()->table('content_items')
            ->where('id', '=', $itemId)
            ->first();

        if ($item === null) {
            return null;
        }

        $campaign = $this->queryFactory->create()->table('campaigns')
            ->where('id', '=', $item['campaign_id'])
            ->whereIn('workspace_id', $ids)
            ->first();

        return $campaign !== null ? $item : null;
    }

    /**
     * Get the user's first workspace (for single-workspace operations).
     */
    public function firstWorkspaceFor(int $userId): ?array
    {
        return $this->queryFactory->create()->table('workspace_user')
            ->select('workspaces.id', 'workspaces.name', 'workspaces.slug')
            ->join('workspaces', 'workspace_user.workspace_id', '=', 'workspaces.id')
            ->where('workspace_user.user_id', '=', $userId)
            ->first();
    }

    /**
     * Get all workspaces for a user.
     */
    public function workspacesFor(int $userId): array
    {
        return $this->queryFactory->create()->table('workspace_user')
            ->select('workspaces.id', 'workspaces.name', 'workspaces.slug')
            ->join('workspaces', 'workspace_user.workspace_id', '=', 'workspaces.id')
            ->where('workspace_user.user_id', '=', $userId)
            ->get();
    }
}
