<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Context\UserContext;
use App\Dashboard\Http\RequestBodyParser;
use App\Dashboard\Pipeline\AgentPipeline;
use App\Dashboard\Service\AgentModelResolver;
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
        private readonly UserContext $userContext,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly AgentPipeline $agentPipeline,
        private readonly RequestBodyParser $bodyParser,
        private readonly AgentModelResolver $modelResolver,
    ) {}

    #[Get('/api/campaigns/{campaignId}/agents/{agentType}/session')]
    public function getSession(int $campaignId, string $agentType): Response
    {
        $userId = $this->userContext->id();

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
        $userId = $this->userContext->id();
        $message = $this->bodyParser->get($request, 'message');

        if (empty($message)) {
            return Response::json(['error' => 'Message is required.'], 422);
        }

        try {
            $result = $this->agentPipeline->run($userId, $campaignId, $agentType, $message);
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'limit reached') ? 429 : 500;
            return Response::json(['error' => $e->getMessage()], $status);
        }

        return Response::json([
            'message' => $result['response'],
            'session_id' => $result['session_id'],
            'remaining_runs' => $result['remaining_runs'],
            'model' => $result['model'],
        ]);
    }

    #[Post('/api/campaigns/{campaignId}/agents/{agentType}/save')]
    public function saveToCampaign(Request $request, int $campaignId, string $agentType): Response
    {
        $userId = $this->userContext->id();
        $content = $this->bodyParser->get($request, 'content');
        $platform = $this->bodyParser->get($request, 'platform');

        if (empty($content)) {
            return Response::json(['error' => 'Content is required.'], 422);
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
            'media' => 'media_plan',
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

    #[Get('/api/agents/models')]
    public function agentModels(): Response
    {
        return Response::json([
            'models' => $this->modelResolver->availableModels(),
            'defaults' => $this->modelResolver->defaults(),
        ]);
    }
}
