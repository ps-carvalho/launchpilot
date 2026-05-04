<?php

declare(strict_types=1);

use App\Dashboard\Controller\ContentItemController;

beforeEach(function () {
    $this->controller = $this->container()->get(ContentItemController::class);
});

describe('updateStatus', function () {
    it('transitions draft to approved', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);
        $itemId = $this->createContentItem($campaignId, 'Content', 'draft');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['status' => 'approved'],
            body: '',
        );

        $response = $this->controller->updateStatus($request, $itemId);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(200)
            ->and($data['success'])->toBeTrue()
            ->and($data['status'])->toBe('approved');
    });

    it('sets published_at when transitioning to published', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);
        $itemId = $this->createContentItem($campaignId, 'Content', 'approved');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['status' => 'published'],
            body: '',
        );

        $response = $this->controller->updateStatus($request, $itemId);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(200)
            ->and($data['status'])->toBe('published');

        $item = $this->query()->create()->table('content_items')
            ->where('id', '=', $itemId)
            ->first();
        expect($item['published_at'])->not->toBeNull();
    });

    it('rejects invalid status', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);
        $itemId = $this->createContentItem($campaignId, 'Content', 'draft');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['status' => 'invalid'],
            body: '',
        );

        $response = $this->controller->updateStatus($request, $itemId);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(422)
            ->and($data['errors']['status'])->toContain("The status field must be one of: 'draft', 'approved', 'scheduled', 'published'.");
    });

    it('rejects unauthorized transitions', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);
        $itemId = $this->createContentItem($campaignId, 'Content', 'published');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['status' => 'draft'],
            body: '',
        );

        $response = $this->controller->updateStatus($request, $itemId);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(422)
            ->and($data['error'])->toContain('Cannot transition');
    });

    it('returns 404 for unauthorized item', function () {
        $userId1 = $this->createUser('User One', 'one@test.com');
        $userId2 = $this->createUser('User Two', 'two@test.com');
        $this->loginAsUser($userId2);
        $wsId = $this->createWorkspace($userId1);
        $campaignId = $this->createCampaign($wsId);
        $itemId = $this->createContentItem($campaignId, 'Content', 'draft');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['status' => 'approved'],
            body: '',
        );

        $response = $this->controller->updateStatus($request, $itemId);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(404)
            ->and($data['error'])->toContain('Not found');
    });

    it('allows approved to scheduled to published flow', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);
        $itemId = $this->createContentItem($campaignId, 'Content', 'draft');

        // draft -> approved
        $r1 = $this->controller->updateStatus(new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'], query: [], post: ['status' => 'approved'], body: '',
        ), $itemId);
        expect(json_decode($r1->body(), true)['status'])->toBe('approved');

        // approved -> scheduled
        $r2 = $this->controller->updateStatus(new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'], query: [], post: ['status' => 'scheduled'], body: '',
        ), $itemId);
        expect(json_decode($r2->body(), true)['status'])->toBe('scheduled');

        // scheduled -> published
        $r3 = $this->controller->updateStatus(new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'], query: [], post: ['status' => 'published'], body: '',
        ), $itemId);
        expect(json_decode($r3->body(), true)['status'])->toBe('published');
    });
});

describe('edit', function () {
    it('updates content text', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);
        $itemId = $this->createContentItem($campaignId, 'Original content', 'draft');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['content' => 'Updated content'],
            body: '',
        );

        $response = $this->controller->edit($request, $itemId);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(200)
            ->and($data['success'])->toBeTrue();

        $item = $this->query()->create()->table('content_items')
            ->where('id', '=', $itemId)
            ->first();
        expect($item['content'])->toBe('Updated content');
    });

    it('rejects empty content', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId);
        $itemId = $this->createContentItem($campaignId, 'Content', 'draft');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['content' => ''],
            body: '',
        );

        $response = $this->controller->edit($request, $itemId);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(422)
            ->and($data['errors']['content'])->toContain('The content field is required.');
    });

    it('returns 404 for unauthorized item', function () {
        $userId1 = $this->createUser('User One', 'one@test.com');
        $userId2 = $this->createUser('User Two', 'two@test.com');
        $this->loginAsUser($userId2);
        $wsId = $this->createWorkspace($userId1);
        $campaignId = $this->createCampaign($wsId);
        $itemId = $this->createContentItem($campaignId, 'Content', 'draft');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['content' => 'Hacked'],
            body: '',
        );

        $response = $this->controller->edit($request, $itemId);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(404)
            ->and($data['error'])->toContain('Not found');
    });
});
