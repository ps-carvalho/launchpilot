<?php

declare(strict_types=1);

namespace App\Dashboard\Pipeline;

use App\Dashboard\Context\Builder\ContextBuilderRegistry;
use App\Dashboard\Gate\CampaignGate;
use App\Dashboard\Service\AgentChatService;
use App\Dashboard\Service\AgentModelResolver;
use App\Dashboard\Service\UsageQuota;
use App\Dashboard\Service\VideoGenerationService;
use Marko\Database\Query\QueryBuilderFactoryInterface;

class AgentPipeline
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly AgentChatService $chatService,
        private readonly VideoGenerationService $videoService,
        private readonly ContextBuilderRegistry $contextRegistry,
        private readonly UsageQuota $usageQuota,
        private readonly CampaignGate $campaignGate,
        private readonly AgentModelResolver $modelResolver,
    ) {}

    /**
     * Execute the agent pipeline based on output modality.
     *
     * @return array{response: array<string, mixed>, session_id: int, remaining_runs: int, model: string}
     * @throws \RuntimeException When rate limited, campaign not found, or agent fails.
     */
    public function run(int $userId, int $campaignId, string $modality, string $message): array
    {
        if (!$this->usageQuota->canRun($userId)) {
            throw new \RuntimeException('Daily agent run limit reached.');
        }

        $campaign = $this->campaignGate->forUser($userId, $campaignId);
        if ($campaign === null) {
            throw new \RuntimeException('Campaign not found.');
        }

        $modality = $this->normalizeModality($modality);
        $modelConfig = $this->modelResolver->resolve($userId, $modality);

        return match ($modality) {
            'image' => $this->runImage($userId, $campaignId, $message, $modelConfig),
            'video' => $this->runVideo($userId, $campaignId, $message, $modelConfig),
            default => $this->runText($userId, $campaignId, $modality, $message, $modelConfig),
        };
    }

    /**
     * @param array{model: string, temperature: float, max_tokens: int} $modelConfig
     * @return array{response: array<string, mixed>, session_id: int, remaining_runs: int, model: string}
     */
    private function runText(int $userId, int $campaignId, string $modality, string $message, array $modelConfig): array
    {
        $history = [];
        $sessionId = null;

        $session = $this->queryFactory->create()->table('agent_sessions')
            ->where('campaign_id', '=', $campaignId)
            ->where('agent_type', '=', $modality)
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->first();

        if ($session !== null) {
            $sessionId = (int) $session['id'];
            $history = json_decode($session['messages'] ?? '[]', true);
        }

        $kbContext = $this->contextRegistry->build(
            $modality,
            $message,
            (int) $this->campaignGate->forUser($userId, $campaignId)['workspace_id'],
            $userId,
        );

        $response = $this->chatService->chat(
            $userId,
            $modality,
            $message,
            $history,
            $kbContext,
            $modelConfig,
        );

        if ($response === null) {
            throw new \RuntimeException('Failed to get response from agent.');
        }

        $history[] = ['role' => 'user', 'content' => $message, 'timestamp' => date('c')];
        $history[] = ['role' => 'assistant', 'content' => $response['content'], 'timestamp' => date('c')];

        $sessionId = $this->persistSession($campaignId, $modality, $userId, $history, $sessionId);
        $this->usageQuota->recordRun($userId);

        return [
            'response' => $response,
            'session_id' => $sessionId,
            'remaining_runs' => $this->usageQuota->remaining($userId),
            'model' => $modelConfig['model'],
        ];
    }

    /**
     * @param array{model: string, temperature: float, max_tokens: int} $modelConfig
     * @return array{response: array<string, mixed>, session_id: int, remaining_runs: int, model: string}
     */
    private function runImage(int $userId, int $campaignId, string $message, array $modelConfig): array
    {
        $response = $this->chatService->generateImage($userId, $message, $modelConfig);

        if ($response === null) {
            throw new \RuntimeException('Failed to generate image.');
        }

        $this->usageQuota->recordRun($userId);

        return [
            'response' => $response,
            'session_id' => 0,
            'remaining_runs' => $this->usageQuota->remaining($userId),
            'model' => $modelConfig['model'],
        ];
    }

    /**
     * @param array{model: string, temperature: float, max_tokens: int} $modelConfig
     * @return array{response: array<string, mixed>, session_id: int, remaining_runs: int, model: string}
     */
    private function runVideo(int $userId, int $campaignId, string $message, array $modelConfig): array
    {
        $isPro = $this->usageQuota->tier($userId) === 'pro';

        if (!$isPro) {
            // Free tier: generate script + shot list via text model
            $textModelConfig = $this->modelResolver->resolve($userId, 'text');
            $textResponse = $this->chatService->chat(
                $userId,
                'video',
                $message,
                [],
                [],
                $textModelConfig,
            );

            if ($textResponse === null) {
                throw new \RuntimeException('Failed to generate video script.');
            }

            $textResponse['upgrade_cta'] = true;
            $this->usageQuota->recordRun($userId);

            return [
                'response' => $textResponse,
                'session_id' => 0,
                'remaining_runs' => $this->usageQuota->remaining($userId),
                'model' => $textModelConfig['model'],
            ];
        }

        // Pro tier: submit async video job
        $job = $this->videoService->submit($userId, $message, $modelConfig);

        if ($job === null) {
            throw new \RuntimeException('Failed to submit video generation job.');
        }

        $assetId = $this->videoService->recordJob($campaignId, $job['job_id'], $modelConfig['model'], $message);

        $this->usageQuota->recordRun($userId);

        return [
            'response' => [
                'role' => 'assistant',
                'content' => 'Video generation started. You can track progress in the Media panel.',
                'job_id' => $job['job_id'],
                'asset_id' => $assetId,
            ],
            'session_id' => 0,
            'remaining_runs' => $this->usageQuota->remaining($userId),
            'model' => $modelConfig['model'],
        ];
    }

    /**
     * @param array<int, array{role: string, content: string, timestamp: string}> $history
     */
    private function persistSession(int $campaignId, string $modality, int $userId, array $history, ?int $existingId): int
    {
        if ($existingId === null) {
            return $this->queryFactory->create()->table('agent_sessions')->insert([
                'campaign_id' => $campaignId,
                'agent_type' => $modality,
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

    private function normalizeModality(string $modality): string
    {
        return match ($modality) {
            'social', 'content', 'seo', 'brainstorm', 'media', 'text' => 'text',
            'image', 'video' => $modality,
            default => 'text',
        };
    }
}
