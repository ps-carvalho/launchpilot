<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Service\AgentChatService;
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
        $message = $request->post('message');

        if (empty($message)) {
            return Response::json(['error' => 'Message is required.'], 422);
        }

        // Verify campaign belongs to user's workspace
        $campaign = $this->getCampaign($campaignId, $userId);
        if ($campaign === null) {
            return Response::json(['error' => 'Campaign not found.'], 404);
        }

        // Get or create session
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

        // Get KB context via vector search
        $kbContext = [];
        $workspaceId = (int) $campaign['workspace_id'];

        // Embed the user's message to find relevant KB chunks
        $embeddings = (new \App\Dashboard\Service\EmbeddingService())->embed([$message]);
        if ($embeddings !== null && !empty($embeddings[0])) {
            $kbContext = $this->vectorSearch->search($embeddings[0], 3, $workspaceId);
        }

        // If vector search returns nothing, fall back to raw document text
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

        // Call the agent
        $response = $this->chatService->chat($agentType, $message, $history, $kbContext);

        if ($response === null) {
            return Response::json(['error' => 'Failed to get response from agent.'], 500);
        }

        // Save messages to session
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

        return Response::json([
            'message' => $response,
            'session_id' => $sessionId,
        ]);
    }

    #[Post('/api/campaigns/{campaignId}/agents/{agentType}/save')]
    public function saveToCampaign(Request $request, int $campaignId, string $agentType): Response
    {
        $userId = $this->auth->id() ?? 0;
        $content = $request->post('content');
        $platform = $request->post('platform');

        if (empty($content)) {
            return Response::json(['error' => 'Content is required.'], 422);
        }

        $campaign = $this->getCampaign($campaignId, $userId);
        if ($campaign === null) {
            return Response::json(['error' => 'Campaign not found.'], 404);
        }

        // Get the latest session for this agent
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
