<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Context\Builder\ContextBuilderRegistry;
use App\User\Context\UserContext;
use App\Dashboard\Gate\CampaignGate;
use App\Dashboard\Http\RequestBodyParser;
use App\Dashboard\Job\DownloadVideoJob;
use App\Dashboard\Pipeline\AgentPipeline;
use App\Dashboard\Service\AgentChatService;
use App\Dashboard\Service\AgentModelResolver;
use App\Dashboard\Service\UsageQuota;
use App\Dashboard\Service\VideoGenerationService;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Inertia\Middleware\InertiaMiddleware;
use Marko\Queue\QueueInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Sse\SseEvent;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;
use Marko\Validation\Contracts\ValidatorInterface;

#[Middleware([SessionMiddleware::class, AuthMiddleware::class, InertiaMiddleware::class])]
class AgentController
{
    public function __construct(
        private readonly UserContext $userContext,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly AgentPipeline $agentPipeline,
        private readonly RequestBodyParser $bodyParser,
        private readonly AgentModelResolver $modelResolver,
        private readonly VideoGenerationService $videoService,
        private readonly QueueInterface $queue,
        private readonly AgentChatService $chatService,
        private readonly ContextBuilderRegistry $contextRegistry,
        private readonly CampaignGate $campaignGate,
        private readonly UsageQuota $usageQuota,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Get('/api/campaigns/{campaignId}/agents/{modality}/stream')]
    public function streamChat(int $campaignId, string $modality, Request $request): Response
    {
        $userId = $this->userContext->id();

        $campaign = $this->campaignGate->forUser($userId, $campaignId);
        if ($campaign === null) {
            return Response::json(['error' => 'Campaign not found.'], 404);
        }

        $data = $request->query('message') ?? '';
        if (trim($data) === '') {
            return Response::json(['error' => 'Message is required.'], 422);
        }

        $modality = $this->normalizeModality($modality);
        $modelConfig = $this->modelResolver->resolve($userId, $modality);

        $history = [];
        $session = $this->queryFactory->create()->table('agent_sessions')
            ->where('campaign_id', '=', $campaignId)
            ->where('agent_type', '=', $modality)
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->first();

        if ($session !== null) {
            $history = json_decode($session['messages'] ?? '[]', true);
        }

        $kbContext = $this->contextRegistry->build(
            $modality,
            $data,
            (int) $campaign['workspace_id'],
            $userId,
        );

        // Send SSE headers and stream tokens
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_implicit_flush(true);
        set_time_limit(0);

        $fullResponse = '';

        $this->chatService->streamChat(
            $userId,
            $modality,
            $data,
            $history,
            $kbContext,
            function (string $token) use (&$fullResponse): void {
                $fullResponse .= $token;
                $data = json_encode(['token' => $token], JSON_THROW_ON_ERROR);
                echo "event: token\ndata: {$data}\n\n";
                flush();
            },
            $modelConfig,
        );

        // Persist session with full response
        $history[] = ['role' => 'user', 'content' => $data, 'timestamp' => date('c')];
        $history[] = ['role' => 'assistant', 'content' => $fullResponse, 'timestamp' => date('c')];
        $this->persistSession($campaignId, $modality, $userId, $history, $session['id'] ?? null);
        $this->usageQuota->recordRun($userId);

        echo "event: done\ndata: " . json_encode([
            'remaining_runs' => $this->usageQuota->remaining($userId),
            'model' => $modelConfig['model'],
        ]) . "\n\n";
        flush();

        return new Response(body: '');
    }

    #[Get('/api/campaigns/{campaignId}/agents/{modality}/session')]
    public function getSession(int $campaignId, string $modality): Response
    {
        $userId = $this->userContext->id();

        $session = $this->queryFactory->create()->table('agent_sessions')
            ->where('campaign_id', '=', $campaignId)
            ->where('agent_type', '=', $modality)
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
                'modality' => $session['agent_type'],
                'created_at' => $session['created_at'],
            ],
            'messages' => $messages,
        ]);
    }

    #[Post('/api/campaigns/{campaignId}/agents/{modality}/chat')]
    public function chat(Request $request, int $campaignId, string $modality): Response
    {
        $userId = $this->userContext->id();
        $data = $this->bodyParser->all($request);
        $errors = $this->validator->validate($data, [
            'message' => 'required|string|min:1',
        ]);

        if ($errors->isNotEmpty()) {
            return Response::json(['errors' => $errors->all()], 422);
        }

        $message = $data['message'];

        try {
            $result = $this->agentPipeline->run($userId, $campaignId, $modality, $message);
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

    #[Post('/api/campaigns/{campaignId}/agents/{modality}/save')]
    public function saveToCampaign(Request $request, int $campaignId, string $modality): Response
    {
        $userId = $this->userContext->id();
        $data = $this->bodyParser->all($request);
        $errors = $this->validator->validate($data, [
            'content' => 'nullable|string',
            'media_asset_id' => 'nullable|integer',
            'platform' => 'nullable|string',
        ]);

        if ($errors->isNotEmpty()) {
            return Response::json(['errors' => $errors->all()], 422);
        }

        $content = $data['content'] ?? null;
        $platform = $data['platform'] ?? null;
        $mediaAssetId = $data['media_asset_id'] ?? null;

        if (empty($content) && empty($mediaAssetId)) {
            return Response::json(['error' => 'Content or media asset is required.'], 422);
        }

        $type = match ($modality) {
            'text', 'social', 'content', 'seo', 'brainstorm', 'media' => 'text_content',
            'image' => 'image',
            'video' => 'video',
            default => 'text_content',
        };

        $itemId = $this->queryFactory->create()->table('content_items')->insert([
            'campaign_id' => $campaignId,
            'type' => $type,
            'platform' => $platform,
            'status' => 'draft',
            'content' => $content ?? '',
            'media_asset_id' => $mediaAssetId ? (int) $mediaAssetId : null,
            'metadata' => json_encode([
                'modality' => $modality,
                'saved_at' => date('c'),
            ]),
        ]);

        return Response::json([
            'item_id' => $itemId,
            'message' => 'Saved to campaign.',
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

    #[Get('/api/campaigns/{campaignId}/media')]
    public function mediaAssets(int $campaignId): Response
    {
        $userId = $this->userContext->id();
        $assets = $this->videoService->assetsForCampaign($campaignId);

        return Response::json([
            'assets' => $assets,
        ]);
    }

    #[Post('/api/campaigns/{campaignId}/media/upload')]
    public function uploadMedia(Request $request, int $campaignId): Response
    {
        $userId = $this->userContext->id();

        // Validate campaign ownership
        $campaign = $this->campaignGate->forUser($userId, $campaignId);
        if ($campaign === null) {
            return Response::json(['error' => 'Campaign not found.'], 404);
        }

        $uploadedFile = $request->files('image');
        $prompt = $this->bodyParser->get($request, 'prompt', '');

        if ($uploadedFile === null || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            return Response::json(['error' => 'No image uploaded.'], 422);
        }

        $dir = '/var/www/storage/media/' . $campaignId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION) ?: 'png';
        $filename = uniqid('img_') . '.' . $ext;
        $localPath = $dir . '/' . $filename;

        move_uploaded_file($uploadedFile['tmp_name'], $localPath);

        $assetId = $this->queryFactory->create()->table('media_assets')->insert([
            'campaign_id' => $campaignId,
            'type' => 'image',
            'local_path' => $localPath,
            'status' => 'ready',
            'metadata' => json_encode(['prompt' => $prompt]),
        ]);

        $asset = $this->queryFactory->create()->table('media_assets')
            ->where('id', '=', $assetId)
            ->first();

        return Response::json(['asset' => $asset]);
    }

    #[Get('/media/{campaignId}/{filename}')]
    public function serveMedia(int $campaignId, string $filename): Response
    {
        $userId = $this->userContext->id();

        $campaign = $this->campaignGate->forUser($userId, $campaignId);
        if ($campaign === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        $path = '/var/www/storage/media/' . $campaignId . '/' . basename($filename);

        if (!file_exists($path)) {
            return Response::json(['error' => 'File not found.'], 404);
        }

        $mimeType = mime_content_type($path) ?: 'application/octet-stream';
        $content = file_get_contents($path);

        return new Response(
            body: $content,
            statusCode: 200,
            headers: [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=31536000',
            ],
        );
    }

    #[Post('/api/media/{assetId}/poll')]
    public function pollVideo(int $assetId): Response
    {
        $userId = $this->userContext->id();

        $asset = $this->queryFactory->create()->table('media_assets')
            ->where('id', '=', $assetId)
            ->first();

        if ($asset === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        $metadata = json_decode($asset['metadata'] ?? '{}', true) ?: [];
        $jobId = $metadata['job_id'] ?? null;

        if (empty($jobId)) {
            return Response::json(['error' => 'No job ID.'], 400);
        }

        $result = $this->videoService->poll($jobId, $userId);

        if ($result === null) {
            return Response::json(['error' => 'Failed to poll.'], 500);
        }

        if ($result['status'] === 'completed' && !empty($result['unsigned_urls'])) {
            $this->queue->push(new DownloadVideoJob(
                assetId: $assetId,
                jobId: $jobId,
                userId: $userId,
                downloadUrl: $result['unsigned_urls'][0],
                campaignId: (int) $asset['campaign_id'],
            ));
        } elseif (in_array($result['status'], ['failed', 'error'], true)) {
            $this->videoService->markFailed($assetId, 'Generation failed.');
        }

        return Response::json([
            'status' => $result['status'],
            'asset' => $this->queryFactory->create()->table('media_assets')
                ->where('id', '=', $assetId)
                ->first(),
        ]);
    }

    #[Get('/api/media/{assetId}/stream')]
    public function streamMedia(int $assetId): StreamingResponse
    {
        $stream = new SseStream(
            dataProvider: function () use ($assetId): array {
                $asset = $this->queryFactory->create()->table('media_assets')
                    ->where('id', '=', $assetId)
                    ->first();

                if ($asset === null) {
                    return [new SseEvent(data: ['error' => 'Not found'], event: 'error')];
                }

                return [new SseEvent(
                    data: ['asset' => $asset],
                    event: 'status',
                    id: $asset['id'] . ':' . strtotime($asset['updated_at'] ?? 'now'),
                )];
            },
            pollInterval: 3,
            timeout: 300,
        );

        return new StreamingResponse($stream);
    }

    private function normalizeModality(string $modality): string
    {
        return match ($modality) {
            'social', 'content', 'seo', 'brainstorm', 'media' => 'text',
            default => $modality,
        };
    }

    /**
     * @param array<int, array{role: string, content: string, timestamp: string}> $history
     */
    private function persistSession(int $campaignId, string $modality, int $userId, array $history, ?int $existingId): void
    {
        if ($existingId === null) {
            $this->queryFactory->create()->table('agent_sessions')->insert([
                'campaign_id' => $campaignId,
                'agent_type' => $modality,
                'user_id' => $userId,
                'messages' => json_encode($history),
            ]);
        } else {
            $this->queryFactory->create()->table('agent_sessions')
                ->where('id', '=', $existingId)
                ->update([
                    'messages' => json_encode($history),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
    }
}
