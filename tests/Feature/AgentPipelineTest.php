<?php

declare(strict_types=1);

use App\Dashboard\Authorization\WorkspaceAuthorization;
use App\Dashboard\Context\Builder\ContextBuilderRegistry;
use App\Dashboard\Context\Builder\GscContextBuilder;
use App\Dashboard\Context\Builder\KnowledgeBaseContextBuilder;
use App\Dashboard\Gate\CampaignGate;
use App\Dashboard\Http\HttpClientInterface;
use App\Dashboard\Pipeline\AgentPipeline;
use App\Dashboard\Repository\KnowledgeBaseRepository;
use App\Dashboard\Service\AgentChatService;
use App\Dashboard\Service\AgentModelResolver;
use App\Dashboard\Service\AgentPromptRegistry;
use App\Dashboard\Service\ApiKeyResolver;
use App\Dashboard\Service\EmbeddingService;
use App\Dashboard\Service\GoogleSearchConsoleService;
use App\Dashboard\Service\UsageQuota;
use Tests\FakeHttpClient;

beforeEach(function () {
    $this->fakeHttp = new FakeHttpClient();

    // Override HttpClientInterface in container
    $this->container()->instance(HttpClientInterface::class, $this->fakeHttp);

    // Re-instantiate services that depend on HttpClientInterface
    $apiKeyResolver = new ApiKeyResolver($this->query());
    $promptRegistry = new AgentPromptRegistry($this->query());
    $chatService = new AgentChatService($apiKeyResolver, $promptRegistry, $this->fakeHttp);
    $embedder = new EmbeddingService($this->fakeHttp);
    $kbRepo = new KnowledgeBaseRepository($this->query());
    $kbBuilder = new KnowledgeBaseContextBuilder($embedder, $kbRepo);
    $gscService = new GoogleSearchConsoleService($this->fakeHttp);
    $gscBuilder = new GscContextBuilder($this->query(), $gscService);

    $registry = new ContextBuilderRegistry();
    $registry->register($kbBuilder);
    $registry->register($gscBuilder);

    $usageQuota = new UsageQuota($this->query());
    $workspaceAuth = new WorkspaceAuthorization($this->query());
    $campaignGate = new CampaignGate($this->query(), $workspaceAuth);
    $modelResolver = new AgentModelResolver($this->query());

    $this->pipeline = new AgentPipeline(
        $this->query(),
        $chatService,
        $registry,
        $usageQuota,
        $campaignGate,
        $modelResolver,
    );

    // Set a valid API key so we don't hit the "not configured" path
    putenv('OPENROUTER_API_KEY=sk-test-key');
});

describe('rate limiting', function () {
    it('throws when free user exceeds daily limit', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'free',
            'daily_runs_used' => 10,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        expect(fn () => $this->pipeline->run($userId, $campaignId, 'social', 'Hello'))
            ->toThrow(\RuntimeException::class, 'Daily agent run limit reached.');
    });

    it('allows pro user past free limit', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'pro',
            'daily_runs_used' => 10,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->fakeHttp->fake(
            'https://openrouter.ai/api/v1/chat/completions',
            200,
            json_encode(['choices' => [['message' => ['content' => 'Pro response']]]]),
        );

        $result = $this->pipeline->run($userId, $campaignId, 'social', 'Hello');

        expect($result['response']['content'])->toBe('Pro response');
    });
});

describe('authorization', function () {
    it('throws when campaign does not belong to user', function () {
        $userA = $this->createUser('User A', 'a@example.com');
        $userB = $this->createUser('User B', 'b@example.com');

        $wsA = $this->createWorkspace($userA);
        $wsB = $this->createWorkspace($userB);
        $campaignB = $this->createCampaign($wsB);

        expect(fn () => $this->pipeline->run($userA, $campaignB, 'social', 'Hello'))
            ->toThrow(\RuntimeException::class, 'Campaign not found.');
    });
});

describe('chat flow', function () {
    it('returns response from LLM and persists session', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);

        $this->fakeHttp->fake(
            'https://openrouter.ai/api/v1/chat/completions',
            200,
            json_encode(['choices' => [['message' => ['content' => 'AI says hello']]]]),
        );

        $result = $this->pipeline->run($userId, $campaignId, 'social', 'Write a post');

        expect($result['response']['content'])->toBe('AI says hello')
            ->and($result['session_id'])->toBeInt()
            ->and($result['remaining_runs'])->toBe(9);

        // Verify session was persisted
        $session = $this->query()->create()->table('agent_sessions')
            ->where('campaign_id', '=', $campaignId)
            ->where('agent_type', '=', 'social')
            ->first();

        expect($session)->not->toBeNull();

        $messages = json_decode($session['messages'], true);
        expect($messages)->toHaveCount(2)
            ->and($messages[0]['role'])->toBe('user')
            ->and($messages[0]['content'])->toBe('Write a post')
            ->and($messages[1]['role'])->toBe('assistant')
            ->and($messages[1]['content'])->toBe('AI says hello');
    });

    it('appends to existing session', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);

        // Pre-create session
        $existingMessages = [
            ['role' => 'user', 'content' => 'First message', 'timestamp' => date('c')],
            ['role' => 'assistant', 'content' => 'First response', 'timestamp' => date('c')],
        ];

        $sessionId = $this->query()->create()->table('agent_sessions')->insert([
            'campaign_id' => $campaignId,
            'agent_type' => 'social',
            'user_id' => $userId,
            'messages' => json_encode($existingMessages),
        ]);

        $this->fakeHttp->fake(
            'https://openrouter.ai/api/v1/chat/completions',
            200,
            json_encode(['choices' => [['message' => ['content' => 'Second response']]]]),
        );

        $result = $this->pipeline->run($userId, $campaignId, 'social', 'Second message');

        expect($result['session_id'])->toBe($sessionId);

        $session = $this->query()->create()->table('agent_sessions')
            ->where('id', '=', $sessionId)
            ->first();

        $messages = json_decode($session['messages'], true);
        expect($messages)->toHaveCount(4);
    });

    it('increments run count after successful run', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);

        $this->fakeHttp->fake(
            'https://openrouter.ai/api/v1/chat/completions',
            200,
            json_encode(['choices' => [['message' => ['content' => 'Ok']]]]),
        );

        $this->pipeline->run($userId, $campaignId, 'social', 'Hi');

        $settings = $this->query()->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();

        expect((int) $settings['daily_runs_used'])->toBe(1);
    });

    it('uses fallback document context when embeddings unavailable', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);
        $this->createDocument($workspaceId, 'Our product helps small businesses grow.');

        // No embedding API key set, so embed() returns null
        putenv('OPENROUTER_API_KEY=');

        $this->fakeHttp->fake(
            'https://openrouter.ai/api/v1/chat/completions',
            200,
            json_encode(['choices' => [['message' => ['content' => 'Got it']]]]),
        );

        $result = $this->pipeline->run($userId, $campaignId, 'content', 'Tell me about my product');

        expect($result['response']['content'])->toBe('Got it');
    });
});

describe('agent types', function () {
    it('handles social agent type', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);

        $this->fakeHttp->fake(
            'https://openrouter.ai/api/v1/chat/completions',
            200,
            json_encode(['choices' => [['message' => ['content' => 'Social post']]]]),
        );

        $result = $this->pipeline->run($userId, $campaignId, 'social', 'Create a LinkedIn post');

        expect($result['response']['content'])->toBe('Social post');
    });

    it('handles content agent type', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);

        $this->fakeHttp->fake(
            'https://openrouter.ai/api/v1/chat/completions',
            200,
            json_encode(['choices' => [['message' => ['content' => 'Blog post']]]]),
        );

        $result = $this->pipeline->run($userId, $campaignId, 'content', 'Write a blog');

        expect($result['response']['content'])->toBe('Blog post');
    });

    it('handles brainstorm agent type', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);

        $this->fakeHttp->fake(
            'https://openrouter.ai/api/v1/chat/completions',
            200,
            json_encode(['choices' => [['message' => ['content' => 'Ideas']]]]),
        );

        $result = $this->pipeline->run($userId, $campaignId, 'brainstorm', 'Brainstorm');

        expect($result['response']['content'])->toBe('Ideas');
    });
});

describe('SEO agent with GSC', function () {
    it('enriches seo agent with GSC data when connected', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);

        // Set up GSC connection
        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'free',
            'daily_runs_used' => 0,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
            'gsc_refresh_token' => 'refresh-token-123',
        ]);

        // Mock GSC token refresh
        $this->fakeHttp->fake(
            'https://oauth2.googleapis.com/token',
            200,
            json_encode(['access_token' => 'gsc-access-token']),
        );

        // Mock GSC sites list
        $this->fakeHttp->fake(
            'https://www.googleapis.com/webmasters/v3/sites',
            200,
            json_encode(['siteEntry' => [['siteUrl' => 'https://example.com']]]),
        );

        // Mock GSC analytics
        $this->fakeHttp->fake(
            'https://www.googleapis.com/webmasters/v3/sites/' . urlencode('https://example.com') . '/searchAnalytics/query',
            200,
            json_encode(['rows' => [
                ['keys' => ['test query'], 'clicks' => 100, 'impressions' => 1000, 'ctr' => 0.1, 'position' => 5.5],
            ]]),
        );

        $this->fakeHttp->fake(
            'https://openrouter.ai/api/v1/chat/completions',
            200,
            json_encode(['choices' => [['message' => ['content' => 'SEO analysis']]]]),
        );

        $result = $this->pipeline->run($userId, $campaignId, 'seo', 'Analyze my SEO');

        expect($result['response']['content'])->toBe('SEO analysis');

        // Verify GSC endpoints were called
        $this->fakeHttp->assertRequested('https://oauth2.googleapis.com/token');
        $this->fakeHttp->assertRequested('https://www.googleapis.com/webmasters/v3/sites');
    });

    it('proceeds without GSC when not connected', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);

        $this->fakeHttp->fake(
            'https://openrouter.ai/api/v1/chat/completions',
            200,
            json_encode(['choices' => [['message' => ['content' => 'Basic SEO']]]]),
        );

        $result = $this->pipeline->run($userId, $campaignId, 'seo', 'SEO tips');

        expect($result['response']['content'])->toBe('Basic SEO');

        // GSC endpoints should NOT be called
        $this->fakeHttp->assertNotRequested('https://oauth2.googleapis.com/token');
    });
});

describe('custom prompts', function () {
    it('uses custom prompt when configured', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);

        // Insert settings with custom prompt
        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'free',
            'daily_runs_used' => 0,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
            'custom_prompts' => json_encode(['social' => 'You are a pirate marketer.']),
        ]);

        $this->fakeHttp->fake(
            'https://openrouter.ai/api/v1/chat/completions',
            200,
            json_encode(['choices' => [['message' => ['content' => 'Arrr!']]]]),
        );

        $result = $this->pipeline->run($userId, $campaignId, 'social', 'Post something');

        // Verify the request body contains our custom prompt
        $requests = $this->fakeHttp->requests();
        $chatRequest = null;
        foreach ($requests as $req) {
            if ($req['url'] === 'https://openrouter.ai/api/v1/chat/completions') {
                $chatRequest = $req;
                break;
            }
        }

        expect($chatRequest)->not->toBeNull();
        $body = json_decode($chatRequest['body'], true);
        expect($body['messages'][0]['content'])->toContain('You are a pirate marketer.');
        expect($result['response']['content'])->toBe('Arrr!');
    });
});
