<?php

declare(strict_types=1);

namespace App\Dashboard\Gate;

use App\Dashboard\Authorization\WorkspaceAuthorization;
use Marko\Database\Query\QueryBuilderFactoryInterface;

/**
 * Centralizes campaign ownership queries and authorization.
 * Encapsulates the workspace-scoped lookup that was repeated across controllers.
 */
class CampaignGate
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly WorkspaceAuthorization $workspaceAuth,
    ) {}

    /**
     * Find a campaign that belongs to one of the user's workspaces.
     * Returns null if not found or not authorized.
     */
    public function forUser(int $userId, int $campaignId): ?array
    {
        return $this->workspaceAuth->campaignFor($userId, $campaignId);
    }

    /**
     * Assert the user owns the campaign and return it, or return null.
     */
    public function forUserOrNull(int $userId, int $campaignId): ?array
    {
        return $this->forUser($userId, $campaignId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function workspacesForUser(int $userId): array
    {
        return $this->workspaceAuth->workspacesFor($userId);
    }

    /**
     * @return array<int>
     */
    public function workspaceIdsForUser(int $userId): array
    {
        return $this->workspaceAuth->workspaceIdsFor($userId);
    }

    /**
     * List all campaigns visible to the user, optionally filtered.
     *
     * @return array<int, array<string, mixed>>
     */
    public function campaignsForUser(int $userId, ?string $status = null, ?bool $archived = null): array
    {
        $workspaceIds = $this->workspaceAuth->workspaceIdsFor($userId);

        if (empty($workspaceIds)) {
            return [];
        }

        $qb = $this->queryFactory->create()->table('campaigns')
            ->whereIn('workspace_id', $workspaceIds);

        if ($status !== null) {
            $qb->where('status', '=', $status);
        }

        if ($archived === true) {
            $qb->whereNotNull('archived_at');
        } elseif ($archived === false) {
            $qb->whereNull('archived_at');
        }

        return $qb->get();
    }
}
