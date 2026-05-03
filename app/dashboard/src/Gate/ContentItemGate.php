<?php

declare(strict_types=1);

namespace App\Dashboard\Gate;

use App\Dashboard\Authorization\WorkspaceAuthorization;
use Marko\Database\Query\QueryBuilderFactoryInterface;

/**
 * Centralizes content item ownership queries.
 */
class ContentItemGate
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly WorkspaceAuthorization $workspaceAuth,
    ) {}

    public function itemForUser(int $userId, int $itemId): ?array
    {
        return $this->workspaceAuth->contentItemFor($userId, $itemId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itemsForCampaign(int $campaignId): array
    {
        return $this->queryFactory->create()->table('content_items')
            ->where('campaign_id', '=', $campaignId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
}
