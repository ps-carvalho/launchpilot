<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Context\UserContext;
use App\Dashboard\Http\RequestBodyParser;
use App\Dashboard\Pipeline\AgentPipeline;
use App\Dashboard\Service\AgentModelResolver;
use App\Dashboard\Service\VideoGenerationService;
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
        private readonly VideoGenerationService $videoService,
    ) {}

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
        $message = $this->bodyParser->get($request, 'message');

        if (empty($message)) {
            return Response::json(['error' => 'Message is required.'], 422);
        }

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
        $content = $this->bodyParser->get($request, 'content');
        $platform = $this->bodyParser->get($request, 'platform');
        $mediaAssetId = $this->bodyParser->get($request, 'media_asset_id');

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
            $filename = $assetId . '_' . time() . '.mp4';
            $localPath = $this->videoService->download(
                $result['unsigned_urls'][0],
                (string) $asset['campaign_id'],
                $filename
            );

            if ($localPath !== null) {
                $this->videoService->markReady($assetId, $localPath, $result['unsigned_urls'][0]);
            } else {
                $this->videoService->markFailed($assetId, 'Download failed.');
            }
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
}
