<?php

declare(strict_types=1);

beforeEach(function () {
    $this->controller = $this->container()->get(\App\Dashboard\Controller\CampaignController::class);
});

describe('index', function () {
    it('returns 200 for user with campaigns', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $this->createCampaign($wsId, 'Active Campaign 1');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: ['tab' => 'active'],
            post: [],
            body: '',
        );

        $response = $this->controller->index($request);

        expect($response->statusCode())->toBe(200);
    });

    it('excludes archived campaigns from active tab', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $this->createCampaign($wsId, 'Active');

        $this->query()->create()->table('campaigns')->insert([
            'workspace_id' => $wsId,
            'title' => 'Archived',
            'status' => 'draft',
            'archived_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: ['tab' => 'active'],
            post: [],
            body: '',
        );

        $response = $this->controller->index($request);
        $body = $response->body();

        expect($response->statusCode())->toBe(200)
            ->and($body)->toContain('Active')
            ->and($body)->not->toContain('Archived');
    });

    it('shows archived campaigns on archived tab', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $this->createCampaign($wsId, 'Active');

        $this->query()->create()->table('campaigns')->insert([
            'workspace_id' => $wsId,
            'title' => 'Archived',
            'status' => 'draft',
            'archived_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: ['tab' => 'archived'],
            post: [],
            body: '',
        );

        $response = $this->controller->index($request);
        $body = $response->body();

        expect($response->statusCode())->toBe(200)
            ->and($body)->toContain('Archived')
            ->and($body)->not->toContain('Active');
    });

    it('returns 200 for user with no campaigns', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->index($request);

        expect($response->statusCode())->toBe(200);
    });
});

describe('show', function () {
    it('returns 200 for authorized campaign', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId, 'Test Campaign');
        $this->createContentItem($campaignId, 'Some content');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->show($request, $campaignId);

        expect($response->statusCode())->toBe(200);
    });

    it('includes remaining runs in view data', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId, 'Test Campaign');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->show($request, $campaignId);
        $body = $response->body();

        expect($body)->toContain('remainingRuns');
    });

    it('redirects for unauthorized campaign', function () {
        $userA = $this->createUser('User A', 'a@example.com');
        $this->loginAsUser($userA);
        $userB = $this->createUser('User B', 'b@example.com');

        $wsB = $this->createWorkspace($userB);
        $campaignB = $this->createCampaign($wsB);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->show($request, $campaignB);

        expect($response->statusCode())->toBe(302);
    });
});

describe('store', function () {
    it('creates a campaign with all fields', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [
                'workspace_id' => $wsId,
                'title' => 'Summer Launch',
                'description' => 'Our big summer push',
                'type' => 'one_off',
                'goal' => '500 signups',
                'channels' => ['LinkedIn', 'Blog'],
                'start_date' => '2026-06-01',
                'end_date' => '2026-08-31',
            ],
            body: '',
        );

        $response = $this->controller->store($request);
        $data = json_decode($response->body(), true);

        expect($data['success'])->toBeTrue()
            ->and($data['campaign_id'])->toBeInt();

        $campaign = $this->query()->create()->table('campaigns')
            ->where('id', '=', $data['campaign_id'])
            ->first();

        expect($campaign['title'])->toBe('Summer Launch')
            ->and($campaign['description'])->toBe('Our big summer push')
            ->and($campaign['type'])->toBe('one_off')
            ->and($campaign['goal'])->toBe('500 signups')
            ->and($campaign['status'])->toBe('draft')
            ->and($campaign['archived_at'])->toBeNull();
    });

    it('rejects empty title', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['workspace_id' => $wsId, 'title' => '   '],
            body: '',
        );

        $response = $this->controller->store($request);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(422)
            ->and($data['errors']['title'])->toContain('The title field is required.');
    });

    it('rejects invalid workspace', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['workspace_id' => 99999, 'title' => 'Test'],
            body: '',
        );

        $response = $this->controller->store($request);

        expect($response->statusCode())->toBe(403);
    });

    it('rejects invalid type', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['workspace_id' => $wsId, 'title' => 'Test', 'type' => 'invalid'],
            body: '',
        );

        $response = $this->controller->store($request);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(422)
            ->and($data['errors']['type'])->toContain("The type field must be one of: 'one_off', 'recurring', 'ongoing'.");
    });
});

describe('update', function () {
    it('updates campaign fields', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId, 'Old Title');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [
                'title' => 'New Title',
                'description' => 'New description',
                'status' => 'active',
                'type' => 'recurring',
            ],
            body: '',
        );

        $response = $this->controller->update($request, $campaignId);
        $data = json_decode($response->body(), true);

        expect($data['success'])->toBeTrue();

        $campaign = $this->query()->create()->table('campaigns')
            ->where('id', '=', $campaignId)
            ->first();

        expect($campaign['title'])->toBe('New Title')
            ->and($campaign['description'])->toBe('New description')
            ->and($campaign['status'])->toBe('active')
            ->and($campaign['type'])->toBe('recurring');
    });

    it('returns 404 for unauthorized campaign', function () {
        $userA = $this->createUser('User A', 'a@example.com');
        $this->loginAsUser($userA);
        $userB = $this->createUser('User B', 'b@example.com');

        $wsB = $this->createWorkspace($userB);
        $campaignB = $this->createCampaign($wsB);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['title' => 'Hacked'],
            body: '',
        );

        $response = $this->controller->update($request, $campaignB);

        expect($response->statusCode())->toBe(404);
    });

    it('returns 422 when no fields provided', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->update($request, $campaignId);

        expect($response->statusCode())->toBe(422);
    });

    it('ignores invalid status values', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId, 'Test', 'draft');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['status' => 'hacked'],
            body: '',
        );

        $response = $this->controller->update($request, $campaignId);

        // Invalid status is ignored, so no valid fields remain → 422
        expect($response->statusCode())->toBe(422);

        $campaign = $this->query()->create()->table('campaigns')
            ->where('id', '=', $campaignId)
            ->first();

        expect($campaign['status'])->toBe('draft');
    });
});

describe('archive', function () {
    it('archives a campaign', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->archive($request, $campaignId);
        $data = json_decode($response->body(), true);

        expect($data['success'])->toBeTrue();

        $campaign = $this->query()->create()->table('campaigns')
            ->where('id', '=', $campaignId)
            ->first();

        expect($campaign['archived_at'])->not->toBeNull();
    });

    it('returns 404 for unauthorized campaign', function () {
        $userA = $this->createUser('User A', 'a@example.com');
        $this->loginAsUser($userA);
        $userB = $this->createUser('User B', 'b@example.com');

        $wsB = $this->createWorkspace($userB);
        $campaignB = $this->createCampaign($wsB);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->archive($request, $campaignB);

        expect($response->statusCode())->toBe(404);
    });
});

describe('restore', function () {
    it('restores an archived campaign', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->query()->create()->table('campaigns')->insert([
            'workspace_id' => $wsId,
            'title' => 'Archived',
            'status' => 'draft',
            'archived_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->restore($request, $campaignId);
        $data = json_decode($response->body(), true);

        expect($data['success'])->toBeTrue();

        $campaign = $this->query()->create()->table('campaigns')
            ->where('id', '=', $campaignId)
            ->first();

        expect($campaign['archived_at'])->toBeNull();
    });

    it('returns 404 for unauthorized campaign', function () {
        $userA = $this->createUser('User A', 'a@example.com');
        $this->loginAsUser($userA);
        $userB = $this->createUser('User B', 'b@example.com');

        $wsB = $this->createWorkspace($userB);
        $campaignB = $this->query()->create()->table('campaigns')->insert([
            'workspace_id' => $wsB,
            'title' => 'Archived',
            'status' => 'draft',
            'archived_at' => date('Y-m-d H:i:s'),
        ]);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->restore($request, $campaignB);

        expect($response->statusCode())->toBe(404);
    });
});

describe('create page', function () {
    it('renders create page with workspaces', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId, 'My Workspace');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->create($request);

        expect($response->statusCode())->toBe(200);
    });

    it('redirects when user has no workspaces', function () {
        $userId = $this->createUser('No WS', 'nows@example.com');
        $this->loginAsUser($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->create($request);

        expect($response->statusCode())->toBe(302);
    });
});
