<?php

declare(strict_types=1);

namespace App\Dashboard\Context\Builder;

use App\Dashboard\Service\GoogleSearchConsoleService;
use Marko\Database\Query\QueryBuilderFactoryInterface;

/**
 * Builds context from Google Search Console data for the SEO agent.
 */
class GscContextBuilder implements AgentContextBuilder
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly GoogleSearchConsoleService $gscService,
    ) {}

    public function build(string $message, int $workspaceId, int $userId): array
    {
        $settings = $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();

        if (empty($settings['gsc_refresh_token'])) {
            return [];
        }

        $tokens = $this->gscService->refreshToken($settings['gsc_refresh_token']);
        if ($tokens === null || empty($tokens['access_token'])) {
            return [];
        }

        $sites = $this->gscService->listSites($tokens['access_token']);
        if (empty($sites[0]['siteUrl'])) {
            return [];
        }

        $data = $this->gscService->getSearchAnalytics(
            $tokens['access_token'],
            $sites[0]['siteUrl'],
            date('Y-m-d', strtotime('-30 days')),
            date('Y-m-d')
        );

        if (empty($data)) {
            return [];
        }

        $gscText = "Google Search Console Data (last 30 days):\n";
        foreach (array_slice($data, 0, 10) as $row) {
            $query = $row['keys'][0] ?? 'unknown';
            $clicks = $row['clicks'] ?? 0;
            $impressions = $row['impressions'] ?? 0;
            $ctr = round(($row['ctr'] ?? 0) * 100, 2);
            $position = round($row['position'] ?? 0, 1);
            $gscText .= "- Query: '{$query}' | Clicks: {$clicks} | Impressions: {$impressions} | CTR: {$ctr}% | Position: {$position}\n";
        }

        return [
            [
                'original_name' => 'Google Search Console',
                'chunk_text' => $gscText,
            ],
        ];
    }

    public function supports(string $agentType): bool
    {
        return $agentType === 'seo';
    }
}
