<?php

declare(strict_types=1);

use App\Dashboard\Authorization\WorkspaceAuthorization;

beforeEach(function () {
    $this->service = $this->container()->get(WorkspaceAuthorization::class);
});

describe('workspaceIdsFor', function () {
    it('returns empty array for user with no workspaces', function () {
        $userId = $this->createUser();

        expect($this->service->workspaceIdsFor($userId))->toBe([]);
    });

    it('returns workspace ids for a user', function () {
        $userId = $this->createUser();
        $ws1 = $this->createWorkspace($userId, 'Workspace One');
        $ws2 = $this->createWorkspace($userId, 'Workspace Two');

        $ids = $this->service->workspaceIdsFor($userId);

        expect($ids)->toHaveCount(2)
            ->and($ids)->toContain($ws1)
            ->and($ids)->toContain($ws2);
    });

    it('caches results between calls', function () {
        $userId = $this->createUser();
        $this->createWorkspace($userId);

        $first = $this->service->workspaceIdsFor($userId);

        // Add another workspace directly, bypassing cache
        $this->query()->create()->table('workspaces')->insert([
            'name' => 'Extra',
            'slug' => 'extra-' . uniqid(),
            'owner_id' => $userId,
        ]);

        $second = $this->service->workspaceIdsFor($userId);

        expect($second)->toEqual($first);
    });

    it('returns fresh results after clearCache', function () {
        $userId = $this->createUser();
        $ws1 = $this->createWorkspace($userId);

        $first = $this->service->workspaceIdsFor($userId);

        $ws2 = $this->query()->create()->table('workspaces')->insert([
            'name' => 'Extra',
            'slug' => 'extra-' . uniqid(),
            'owner_id' => $userId,
        ]);

        $this->query()->create()->table('workspace_user')->insert([
            'workspace_id' => $ws2,
            'user_id' => $userId,
            'role' => 'owner',
        ]);

        $this->service->clearCache();
        $second = $this->service->workspaceIdsFor($userId);

        expect($second)->toHaveCount(2)
            ->and($second)->not->toEqual($first);
    });
});

describe('campaignFor', function () {
    it('returns campaign when user owns workspace', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);

        $campaign = $this->service->campaignFor($userId, $campaignId);

        expect($campaign)->not->toBeNull()
            ->and($campaign['id'])->toBe($campaignId);
    });

    it('returns null when campaign belongs to another workspace', function () {
        $userA = $this->createUser('User A', 'a@example.com');
        $userB = $this->createUser('User B', 'b@example.com');

        $wsA = $this->createWorkspace($userA);
        $wsB = $this->createWorkspace($userB);

        $campaignB = $this->createCampaign($wsB);

        expect($this->service->campaignFor($userA, $campaignB))->toBeNull();
    });

    it('returns null for non-existent campaign', function () {
        $userId = $this->createUser();
        $this->createWorkspace($userId);

        expect($this->service->campaignFor($userId, 99999))->toBeNull();
    });
});

describe('documentFor', function () {
    it('returns document when user owns workspace', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $docId = $this->createDocument($workspaceId);

        $doc = $this->service->documentFor($userId, $docId);

        expect($doc)->not->toBeNull()
            ->and($doc['id'])->toBe($docId);
    });

    it('returns null when document belongs to another workspace', function () {
        $userA = $this->createUser('User A', 'a@example.com');
        $userB = $this->createUser('User B', 'b@example.com');

        $wsA = $this->createWorkspace($userA);
        $wsB = $this->createWorkspace($userB);

        $docB = $this->createDocument($wsB);

        expect($this->service->documentFor($userA, $docB))->toBeNull();
    });
});

describe('contentItemFor', function () {
    it('returns content item when user owns workspace via campaign', function () {
        $userId = $this->createUser();
        $workspaceId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($workspaceId);
        $itemId = $this->createContentItem($campaignId);

        $item = $this->service->contentItemFor($userId, $itemId);

        expect($item)->not->toBeNull()
            ->and($item['id'])->toBe($itemId);
    });

    it('returns null when item campaign belongs to another workspace', function () {
        $userA = $this->createUser('User A', 'a@example.com');
        $userB = $this->createUser('User B', 'b@example.com');

        $wsA = $this->createWorkspace($userA);
        $wsB = $this->createWorkspace($userB);

        $campaignB = $this->createCampaign($wsB);
        $itemB = $this->createContentItem($campaignB);

        expect($this->service->contentItemFor($userA, $itemB))->toBeNull();
    });

    it('returns null for non-existent item', function () {
        $userId = $this->createUser();
        $this->createWorkspace($userId);

        expect($this->service->contentItemFor($userId, 99999))->toBeNull();
    });
});

describe('firstWorkspaceFor', function () {
    it('returns first workspace for user', function () {
        $userId = $this->createUser();
        $this->createWorkspace($userId, 'First');
        $this->createWorkspace($userId, 'Second');

        $workspace = $this->service->firstWorkspaceFor($userId);

        expect($workspace)->not->toBeNull()
            ->and($workspace['name'])->toBe('First');
    });

    it('returns null when user has no workspaces', function () {
        $userId = $this->createUser();

        expect($this->service->firstWorkspaceFor($userId))->toBeNull();
    });
});

describe('workspacesFor', function () {
    it('returns all workspaces for user', function () {
        $userId = $this->createUser();
        $this->createWorkspace($userId, 'Alpha');
        $this->createWorkspace($userId, 'Beta');

        $workspaces = $this->service->workspacesFor($userId);

        expect($workspaces)->toHaveCount(2);

        $names = array_column($workspaces, 'name');
        expect($names)->toContain('Alpha')
            ->and($names)->toContain('Beta');
    });

    it('returns empty array when user has no workspaces', function () {
        $userId = $this->createUser();

        expect($this->service->workspacesFor($userId))->toBe([]);
    });

    it('does not include other users workspaces', function () {
        $userA = $this->createUser('User A', 'a@example.com');
        $userB = $this->createUser('User B', 'b@example.com');

        $this->createWorkspace($userA, 'Alpha');
        $this->createWorkspace($userB, 'Beta');

        $workspaces = $this->service->workspacesFor($userA);

        expect($workspaces)->toHaveCount(1)
            ->and($workspaces[0]['name'])->toBe('Alpha');
    });
});
