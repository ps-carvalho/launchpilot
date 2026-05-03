<?php

declare(strict_types=1);

use App\Dashboard\Controller\AgentController;
use App\Dashboard\Pipeline\AgentPipeline;

beforeEach(function () {
    $this->controller = $this->container()->get(AgentController::class);
});

describe('getSession', function () {
    it('returns session with messages when one exists', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $messages = [
            ['role' => 'user', 'content' => 'Hello', 'timestamp' => date('c')],
            ['role' => 'assistant', 'content' => 'Hi there', 'timestamp' => date('c')],
        ];

        $this->query()->create()->table('agent_sessions')->insert([
            'campaign_id' => $campaignId,
            'agent_type' => 'social',
            'user_id' => $userId,
            'messages' => json_encode($messages),
        ]);

        $response = $this->controller->getSession($campaignId, 'social');
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(200)
            ->and($data['session']['agent_type'])->toBe('social')
            ->and($data['messages'])->toHaveCount(2);
    });

    it('returns null session when none exists', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $response = $this->controller->getSession($campaignId, 'social');
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(200)
            ->and($data['session'])->toBeNull()
            ->and($data['messages'])->toBe([]);
    });

    it('returns only sessions for the requesting user', function () {
        $userId1 = $this->createUser('User One', 'one@test.com');
        $userId2 = $this->createUser('User Two', 'two@test.com');
        $this->loginAsUser($userId2);
        $wsId = $this->createWorkspace($userId1);
        $campaignId = $this->createCampaign($wsId);

        $this->query()->create()->table('agent_sessions')->insert([
            'campaign_id' => $campaignId,
            'agent_type' => 'social',
            'user_id' => $userId1,
            'messages' => json_encode([['role' => 'user', 'content' => 'Hello']]),
        ]);

        $response = $this->controller->getSession($campaignId, 'social');
        $data = json_decode($response->body(), true);

        expect($data['session'])->toBeNull();
    });
});

describe('chat', function () {
    it('returns agent response on success', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $fakePipeline = new class extends AgentPipeline {
            public function __construct() { /* skip parent */ }
            public function run(int $userId, int $campaignId, string $agentType, string $message): array
            {
                return [
                    'response' => ['role' => 'assistant', 'content' => 'Test response'],
                    'session_id' => 42,
                    'remaining_runs' => 8,
                ];
            }
        };
        $this->container()->instance(AgentPipeline::class, $fakePipeline);
        $this->controller = $this->container()->get(AgentController::class);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['message' => 'Hello agent'],
            body: '',
        );

        $response = $this->controller->chat($request, $campaignId, 'social');
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(200)
            ->and($data['message']['content'])->toBe('Test response')
            ->and($data['session_id'])->toBe(42)
            ->and($data['remaining_runs'])->toBe(8);
    });

    it('returns 422 when message is empty', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['message' => ''],
            body: '',
        );

        $response = $this->controller->chat($request, $campaignId, 'social');
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(422)
            ->and($data['error'])->toContain('Message is required');
    });

    it('returns 429 when rate limited', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $fakePipeline = new class extends AgentPipeline {
            public function __construct() { /* skip parent */ }
            public function run(int $userId, int $campaignId, string $agentType, string $message): array
            {
                throw new \RuntimeException('Daily agent run limit reached.');
            }
        };
        $this->container()->instance(AgentPipeline::class, $fakePipeline);
        $this->controller = $this->container()->get(AgentController::class);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['message' => 'Hello'],
            body: '',
        );

        $response = $this->controller->chat($request, $campaignId, 'social');
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(429)
            ->and($data['error'])->toContain('limit reached');
    });

    it('returns 500 on pipeline failure', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $fakePipeline = new class extends AgentPipeline {
            public function __construct() { /* skip parent */ }
            public function run(int $userId, int $campaignId, string $agentType, string $message): array
            {
                throw new \RuntimeException('Agent service unavailable');
            }
        };
        $this->container()->instance(AgentPipeline::class, $fakePipeline);
        $this->controller = $this->container()->get(AgentController::class);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['message' => 'Hello'],
            body: '',
        );

        $response = $this->controller->chat($request, $campaignId, 'social');
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(500)
            ->and($data['error'])->toContain('unavailable');
    });
});

describe('saveToCampaign', function () {
    it('creates content item from agent output', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $sessionId = $this->query()->create()->table('agent_sessions')->insert([
            'campaign_id' => $campaignId,
            'agent_type' => 'social',
            'user_id' => $userId,
            'messages' => '[]',
        ]);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['content' => 'Generated post', 'platform' => 'linkedin'],
            body: '',
        );

        $response = $this->controller->saveToCampaign($request, $campaignId, 'social');
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(200)
            ->and($data['item_id'])->toBeInt()
            ->and($data['message'])->toContain('saved');

        $item = $this->query()->create()->table('content_items')
            ->where('id', '=', $data['item_id'])
            ->first();
        expect($item['content'])->toBe('Generated post')
            ->and($item['type'])->toBe('social_post')
            ->and($item['platform'])->toBe('linkedin')
            ->and($item['status'])->toBe('draft');
    });

    it('creates blog_post type for content agent', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['content' => 'Blog draft'],
            body: '',
        );

        $response = $this->controller->saveToCampaign($request, $campaignId, 'content');
        $data = json_decode($response->body(), true);

        $item = $this->query()->create()->table('content_items')
            ->where('id', '=', $data['item_id'])
            ->first();
        expect($item['type'])->toBe('blog_post');
    });

    it('creates seo_report type for seo agent', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['content' => 'SEO tips'],
            body: '',
        );

        $response = $this->controller->saveToCampaign($request, $campaignId, 'seo');
        $data = json_decode($response->body(), true);

        $item = $this->query()->create()->table('content_items')
            ->where('id', '=', $data['item_id'])
            ->first();
        expect($item['type'])->toBe('seo_report');
    });

    it('creates brainstorm_note type for brainstorm agent', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['content' => 'Ideas'],
            body: '',
        );

        $response = $this->controller->saveToCampaign($request, $campaignId, 'brainstorm');
        $data = json_decode($response->body(), true);

        $item = $this->query()->create()->table('content_items')
            ->where('id', '=', $data['item_id'])
            ->first();
        expect($item['type'])->toBe('brainstorm_note');
    });

    it('returns 422 when content is empty', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['content' => ''],
            body: '',
        );

        $response = $this->controller->saveToCampaign($request, $campaignId, 'social');
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(422)
            ->and($data['error'])->toContain('Content is required');
    });

    it('links to most recent session', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $olderSession = $this->query()->create()->table('agent_sessions')->insert([
            'campaign_id' => $campaignId,
            'agent_type' => 'social',
            'user_id' => $userId,
            'messages' => '[]',
        ]);

        $newerSession = $this->query()->create()->table('agent_sessions')->insert([
            'campaign_id' => $campaignId,
            'agent_type' => 'social',
            'user_id' => $userId,
            'messages' => '[]',
        ]);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['content' => 'Test'],
            body: '',
        );

        $response = $this->controller->saveToCampaign($request, $campaignId, 'social');
        $data = json_decode($response->body(), true);

        $item = $this->query()->create()->table('content_items')
            ->where('id', '=', $data['item_id'])
            ->first();
        expect((int) $item['agent_session_id'])->toBe($newerSession);
    });
});
