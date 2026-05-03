<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use Marko\Database\Query\QueryBuilderFactoryInterface;

class ExportService
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    public function exportKnowledgeBase(int $workspaceId): string
    {
        $documents = $this->queryFactory->create()->table('knowledge_documents')
            ->where('workspace_id', '=', $workspaceId)
            ->orderBy('created_at', 'ASC')
            ->get();

        $campaigns = $this->queryFactory->create()->table('campaigns')
            ->where('workspace_id', '=', $workspaceId)
            ->orderBy('created_at', 'ASC')
            ->get();

        $campaignIds = array_column($campaigns, 'id');
        $contentItems = [];

        if (!empty($campaignIds)) {
            $contentItems = $this->queryFactory->create()->table('content_items')
                ->whereIn('campaign_id', $campaignIds)
                ->orderBy('created_at', 'ASC')
                ->get();
        }

        $md = "# LaunchPilot Knowledge Base Export\n\n";
        $md .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $md .= "---\n\n";

        // Documents section
        $md .= "## Knowledge Documents\n\n";
        foreach ($documents as $doc) {
            $meta = json_decode($doc['metadata'] ?? '{}', true);
            $md .= "### {$doc['original_name']}\n\n";
            if ($doc['source_url']) {
                $md .= "**Source:** {$doc['source_url']}\n\n";
            }
            if (!empty($meta['title'])) {
                $md .= "**Title:** {$meta['title']}\n\n";
            }
            if (!empty($meta['description'])) {
                $md .= "**Description:** {$meta['description']}\n\n";
            }
            $md .= "```\n" . $doc['raw_text'] . "\n```\n\n";
            $md .= "---\n\n";
        }

        // Campaigns & Content section
        if (!empty($campaigns)) {
            $md .= "## Campaigns & Generated Content\n\n";
            foreach ($campaigns as $campaign) {
                $md .= "### {$campaign['title']}\n\n";
                if ($campaign['description']) {
                    $md .= "{$campaign['description']}\n\n";
                }
                if ($campaign['goal']) {
                    $md .= "**Goal:** {$campaign['goal']}\n\n";
                }

                $items = array_filter($contentItems, fn($item) => $item['campaign_id'] == $campaign['id']);
                if (!empty($items)) {
                    foreach ($items as $item) {
                        $md .= "#### {$item['type']} ({$item['status']})\n\n";
                        $md .= $item['content'] . "\n\n";
                    }
                } else {
                    $md .= "*No content items yet.*\n\n";
                }
                $md .= "---\n\n";
            }
        }

        return $md;
    }
}
