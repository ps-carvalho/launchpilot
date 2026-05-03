<?php

declare(strict_types=1);

use App\Dashboard\Controller\CampaignController;

beforeEach(function () {
    $this->controller = $this->container()->get(CampaignController::class);
});

describe('export', function () {
    it('returns markdown file for authorized campaign', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId, 'Summer Launch');
        $this->createContentItem($campaignId, 'Post content', 'draft');

        $response = $this->controller->export($campaignId);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toContain('Summer Launch')
            ->and($response->body())->toContain('Post content');
    });

    it('sets content-disposition header with sanitized filename', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId, 'My Campaign!');

        $response = $this->controller->export($campaignId);

        $headers = $response->headers();
        expect($headers['Content-Disposition'] ?? '')->toContain('my-campaign--export.md');
    });

    it('redirects for unauthorized campaign', function () {
        $userId1 = $this->createUser('User One', 'one@test.com');
        $userId2 = $this->createUser('User Two', 'two@test.com');
        $this->loginAsUser($userId2);
        $wsId = $this->createWorkspace($userId1);
        $campaignId = $this->createCampaign($wsId, 'Private');

        $response = $this->controller->export($campaignId);

        expect($response->statusCode())->toBe(302);
    });
});
