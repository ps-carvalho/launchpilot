<?php

declare(strict_types=1);

namespace App\Dashboard\Pipeline;

use App\Dashboard\Context\Builder\ContextBuilderRegistry;
use App\Dashboard\Gate\CampaignGate;
use App\Dashboard\Service\AgentChatService;
use App\Dashboard\Service\UsageQuota;
use Marko\Database\Query\QueryBuilderFactoryInterface;

class AgentPipeline
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly AgentChatService $chatService,
        private readonly ContextBuilderRegistry $contextRegistry,
        private readonly UsageQuota $usageQuota,
        private readonly CampaignGate $campaignGate,
    ) {}

    /**
     * Execute the full agent chat pipeline.
     *
     * @return array{response: array{role: string, content: string}, session_id: int, remaining_runs: int}
     * @throws \RuntimeException When rate limited, campaign not found, or agent fails.
     */
    public function run(int $userId, int $campaignId, string $agentType, string $message): array
    {
        if (!$this->usageQuota->canRun($userId)) {
            throw new \RuntimeException('Daily agent run limit reached.');
        }

        $campaign = $this->campaignGate->forUser($userId, $campaignId);
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

        $kbContext = $this->contextRegistry->build(
            $agentType,
            $message,
            (int) $campaign['workspace_id'],
            $userId,
        );

        $response = $this->chatService->chat($userId, $agentType, $message, $history, $kbContext);

        if ($response === null) {
            throw new \RuntimeException('Failed to get response from agent.');
        }

        $history[] = ['role' => 'user', 'content' => $message, 'timestamp' => date('c')];
        $history[] = ['role' => 'assistant', 'content' => $response['content'], 'timestamp' => date('c')];

        $sessionId = $this->persistSession($campaignId, $agentType, $userId, $history, $sessionId);
        $this->usageQuota->recordRun($userId);

        return [
            'response' => $response,
            'session_id' => $sessionId,
            'remaining_runs' => $this->usageQuota->remaining($userId),
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
