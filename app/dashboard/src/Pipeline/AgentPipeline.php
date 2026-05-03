<?php

declare(strict_types=1);

namespace App\Dashboard\Pipeline;

use App\Dashboard\Authorization\WorkspaceAuthorization;
use App\Dashboard\Service\AgentChatService;
use App\Dashboard\Service\EmbeddingService;
use App\Dashboard\Service\GoogleSearchConsoleService;
use App\Dashboard\Service\UserSettingsService;
use App\Dashboard\Service\VectorSearchService;
use Marko\Database\Query\QueryBuilderFactoryInterface;

class AgentPipeline
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly AgentChatService $chatService,
        private readonly VectorSearchService $vectorSearch,
        private readonly EmbeddingService $embedder,
        private readonly UserSettingsService $userSettings,
        private readonly GoogleSearchConsoleService $gscService,
        private readonly WorkspaceAuthorization $workspaceAuth,
    ) {}

    /**
     * Execute the full agent chat pipeline.
     *
     * @return array{response: array{role: string, content: string}, session_id: int, remaining_runs: int}
     * @throws \RuntimeException When rate limited, campaign not found, or agent fails.
     */
    public function run(int $userId, int $campaignId, string $agentType, string $message): array
    {
        if (!$this->userSettings->canRunAgent($userId)) {
            throw new \RuntimeException('Daily agent run limit reached.');
        }

        $campaign = $this->workspaceAuth->campaignFor($userId, $campaignId);
        if ($campaign === null) {
            throw new \RuntimeException('Campaign not found.');
        }

        $history = [];
        $sessionId = null;

        $session = $this->queryFactory->create()->table('agent_sessions')
            ->where('campaign_id', '=', $campaignId)
            ->where('agent_type', '=', $agentType)
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->first();

        if ($session !== null) {
            $sessionId = (int) $session['id'];
            $history = json_decode($session['messages'] ?? '[]', true);
        }

        $kbContext = $this->buildKnowledgeBaseContext($message, (int) $campaign['workspace_id']);

        if ($agentType === 'seo') {
            $gscContext = $this->buildGscContext($userId);
            if ($gscContext !== null) {
                $kbContext[] = $gscContext;
            }
        }

        $response = $this->chatService->chat($userId, $agentType, $message, $history, $kbContext);

        if ($response === null) {
            throw new \RuntimeException('Failed to get response from agent.');
        }

        $history[] = ['role' => 'user', 'content' => $message, 'timestamp' => date('c')];
        $history[] = ['role' => 'assistant', 'content' => $response['content'], 'timestamp' => date('c')];

        $sessionId = $this->persistSession($campaignId, $agentType, $userId, $history, $sessionId);
        $this->userSettings->incrementRunCount($userId);

        return [
            'response' => $response,
            'session_id' => $sessionId,
            'remaining_runs' => $this->userSettings->getRemainingRuns($userId),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildKnowledgeBaseContext(string $message, int $workspaceId): array
    {
        $embeddings = $this->embedder->embed([$message]);
        $context = [];

        if ($embeddings !== null && !empty($embeddings[0])) {
            $context = $this->vectorSearch->search($embeddings[0], 3, $workspaceId);
        }

        if (empty($context)) {
            $docs = $this->queryFactory->create()->table('knowledge_documents')
                ->where('workspace_id', '=', $workspaceId)
                ->limit(3)
                ->get();

            foreach ($docs as $doc) {
                $context[] = [
                    'original_name' => $doc['original_name'],
                    'chunk_text' => substr($doc['raw_text'], 0, 2000),
                ];
            }
        }

        return $context;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildGscContext(int $userId): ?array
    {
        $settings = $this->userSettings->getOrCreate($userId);
        if (empty($settings['gsc_refresh_token'])) {
            return null;
        }

        $tokens = $this->gscService->refreshToken($settings['gsc_refresh_token']);
        if ($tokens === null || empty($tokens['access_token'])) {
            return null;
        }

        $sites = $this->gscService->listSites($tokens['access_token']);
        if (empty($sites[0]['siteUrl'])) {
            return null;
        }

        $data = $this->gscService->getSearchAnalytics(
            $tokens['access_token'],
            $sites[0]['siteUrl'],
            date('Y-m-d', strtotime('-30 days')),
            date('Y-m-d')
        );

        if (empty($data)) {
            return null;
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
            'original_name' => 'Google Search Console',
            'chunk_text' => $gscText,
        ];
    }

    /**
     * @param array<int, array{role: string, content: string, timestamp: string}> $history
     */
    private function persistSession(int $campaignId, string $agentType, int $userId, array $history, ?int $existingId): int
    {
        if ($existingId === null) {
            return $this->queryFactory->create()->table('agent_sessions')->insert([
                'campaign_id' => $campaignId,
                'agent_type' => $agentType,
                'user_id' => $userId,
                'messages' => json_encode($history),
            ]);
        }

        $this->queryFactory->create()->table('agent_sessions')
            ->where('id', '=', $existingId)
            ->update([
                'messages' => json_encode($history),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $existingId;
    }
}
