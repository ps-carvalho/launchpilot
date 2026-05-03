<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Helper\JsonInput;
use App\Dashboard\Service\AgentChatService;
use App\Dashboard\Service\EmbeddingService;
use App\Dashboard\Service\GoogleSearchConsoleService;
use App\Dashboard\Service\UserSettingsService;
use App\Dashboard\Service\VectorSearchService;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Inertia\Middleware\InertiaMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Middleware\SessionMiddleware;

#[Middleware([SessionMiddleware::class, AuthMiddleware::class, InertiaMiddleware::class])]
class AgentController
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly AgentChatService $chatService,
        private readonly VectorSearchService $vectorSearch,
        private readonly UserSettingsService $userSettings,
    ) {}

    #[Get('/api/campaigns/{campaignId}/agents/{agentType}/session')]
    public function getSession(int $campaignId, string $agentType): Response
    {
        $userId = $this->auth->id() ?? 0;

        $session = $this->queryFactory->create()->table('agent_sessions')
            ->where('campaign_id', '=', $campaignId)
            ->where('agent_type', '=', $agentType)
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->first();

        if ($session === null) {
            return Response::json(['session' => null, 'messages' => []]);
        }

        $messages = json_decode($session['messages'] ?? '[]', true);

        return Response::json([
            'session' => [
                'id' => $session['id'],
                'agent_type' => $session['agent_type'],
                'created_at' => $session['created_at'],
            ],
            'messages' => $messages,
        ]);
    }

    #[Post('/api/campaigns/{campaignId}/agents/{agentType}/chat')]
    public function chat(Request $request, int $campaignId, string $agentType): Response
    {
        $userId = $this->auth->id() ?? 0;
        $message = JsonInput::get($request, 'message');

        if (empty($message)) {
            return Response::json(['error' => 'Message is required.'], 422);
        }

        // Rate limiting
        if (!$this->userSettings->canRunAgent($userId)) {
            $remaining = $this->userSettings->getRemainingRuns($userId);
            return Response::json([
                'error' => 'Daily agent run limit reached. Upgrade to Pro for unlimited usage.',
                'remaining' => $remaining,
            ], 429);
        }

        $campaign = $this->getCampaign($campaignId, $userId);
        if ($campaign === null) {
            return Response::json(['error' => 'Campaign not found.'], 404);
        }

        $session = $this->queryFactory->create()->table('agent_sessions')
            ->where('campaign_id', '=', $campaignId)
            ->where('agent_type', '=', $agentType)
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->first();

        $history = [];
        $sessionId = null;

        if ($session !== null) {
            $sessionId = (int) $session['id'];
            $history = json_decode($session['messages'] ?? '[]', true);
        }

        $kbContext = [];
        $workspaceId = (int) $campaign['workspace_id'];

        $embeddings = (new EmbeddingService())->embed([$message]);
        if ($embeddings !== null && !empty($embeddings[0])) {
            $kbContext = $this->vectorSearch->search($embeddings[0], 3, $workspaceId);
        }

        if (empty($kbContext)) {
            $docs = $this->queryFactory->create()->table('knowledge_documents')
                ->where('workspace_id', '=', $workspaceId)
                ->limit(3)
                ->get();

            foreach ($docs as $doc) {
                $kbContext[] = [
                    'original_name' => $doc['original_name'],
                    'chunk_text' => substr($doc['raw_text'], 0, 2000),
                ];
            }
        }

        // For SEO agent, try to include GSC data
        if ($agentType === 'seo') {
            $settings = $this->userSettings->getOrCreate($userId);
            if (!empty($settings['gsc_refresh_token'])) {
                $gscService = new GoogleSearchConsoleService($this->queryFactory);
                $tokens = $gscService->refreshToken($settings['gsc_refresh_token']);
                if ($tokens !== null && !empty($tokens['access_token'])) {
                    $sites = $gscService->listSites($tokens['access_token']);
                    if (!empty($sites[0]['siteUrl'])) {
                        $data = $gscService->getSearchAnalytics(
                            $tokens['access_token'],
                            $sites[0]['siteUrl'],
                            date('Y-m-d', strtotime('-30 days')),
                            date('Y-m-d')
                        );
                        if (!empty($data)) {
                            $gscText = "Google Search Console Data (last 30 days):\n";
                            foreach (array_slice($data, 0, 10) as $row) {
                                $query = $row['keys'][0] ?? 'unknown';
                                $clicks = $row['clicks'] ?? 0;
                                $impressions = $row['impressions'] ?? 0;
                                $ctr = round(($row['ctr'] ?? 0) * 100, 2);
                                $position = round($row['position'] ?? 0, 1);
                                $gscText .= "- Query: '{$query}' | Clicks: {$clicks} | Impressions: {$impressions} | CTR: {$ctr}% | Position: {$position}\n";
                            }
                            $kbContext[] = [
                                'original_name' => 'Google Search Console',
                                'chunk_text' => $gscText,
                            ];
                        }
                    }
                }
            }
        }

        $response = $this->chatService->chat($userId, $agentType, $message, $history, $kbContext);

        if ($response === null) {
            return Response::json(['error' => 'Failed to get response from agent.'], 500);
        }

        $history[] = ['role' => 'user', 'content' => $message, 'timestamp' => date('c')];
        $history[] = ['role' => 'assistant', 'content' => $response['content'], 'timestamp' => date('c')];

        if ($sessionId === null) {
            $sessionId = $this->queryFactory->create()->table('agent_sessions')->insert([
                'campaign_id' => $campaignId,
                'agent_type' => $agentType,
                'user_id' => $userId,
                'messages' => json_encode($history),
            ]);
        } else {
            $this->queryFactory->create()->table('agent_sessions')
                ->where('id', '=', $sessionId)
                ->update([
                    'messages' => json_encode($history),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }

        $this->userSettings->incrementRunCount($userId);

        return Response::json([
            'message' => $response,
            'session_id' => $sessionId,
            'remaining_runs' => $this->userSettings->getRemainingRuns($userId),
        ]);
    }

    #[Post('/api/campaigns/{campaignId}/agents/{agentType}/save')]
    public function saveToCampaign(Request $request, int $campaignId, string $agentType): Response
    {
        $userId = $this->auth->id() ?? 0;
        $content = JsonInput::get($request, 'content');
        $platform = JsonInput::get($request, 'platform');

        if (empty($content)) {
            return Response::json(['error' => 'Content is required.'], 422);
        }

        $campaign = $this->getCampaign($campaignId, $userId);
        if ($campaign === null) {
            return Response::json(['error' => 'Campaign not found.'], 404);
        }

        $session = $this->queryFactory->create()->table('agent_sessions')
            ->where('campaign_id', '=', $campaignId)
            ->where('agent_type', '=', $agentType)
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->first();

        $type = match ($agentType) {
            'social' => 'social_post',
            'content' => 'blog_post',
            'seo' => 'seo_report',
            'brainstorm' => 'brainstorm_note',
            default => 'social_post',
        };

        $itemId = $this->queryFactory->create()->table('content_items')->insert([
            'campaign_id' => $campaignId,
            'agent_session_id' => $session['id'] ?? null,
            'type' => $type,
            'platform' => $platform,
            'status' => 'draft',
            'content' => $content,
            'metadata' => json_encode([
                'agent_type' => $agentType,
                'saved_at' => date('c'),
            ]),
        ]);

        return Response::json([
            'item_id' => $itemId,
            'message' => 'Content saved to campaign.',
        ]);
    }

    private function getCampaign(int $campaignId, int $userId): ?array
    {
        $workspaceIds = $this->queryFactory->create()->table('workspace_user')
            ->select('workspace_id')
            ->where('user_id', '=', $userId)
            ->get();

        $ids = array_column($workspaceIds, 'workspace_id');

        if (empty($ids)) {
            return null;
        }

        return $this->queryFactory->create()->table('campaigns')
            ->where('id', '=', $campaignId)
            ->whereIn('workspace_id', $ids)
            ->first();
    }
}
