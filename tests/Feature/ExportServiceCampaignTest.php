<?php

declare(strict_types=1);

use App\Dashboard\Service\ExportService;

beforeEach(function () {
    $this->service = new ExportService($this->query());
});

describe('exportCampaign', function () {
    it('returns markdown with campaign title and description', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId, 'Summer Launch');
        $this->query()->create()->table('campaigns')
            ->where('id', '=', $campaignId)
            ->update([
                'description' => 'Our big summer push',
                'goal' => 'Get 1000 signups',
            ]);

        $md = $this->service->exportCampaign($campaignId);

        expect($md)->toContain('# Summer Launch')
            ->and($md)->toContain('Our big summer push')
            ->and($md)->toContain('Get 1000 signups');
    });

    it('includes content items in the export', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId, 'Test Campaign');
        $this->createContentItem($campaignId, 'Social post text', 'approved', 'social_post', 'linkedin');
        $this->createContentItem($campaignId, 'Blog draft text', 'draft', 'blog_post');

        $md = $this->service->exportCampaign($campaignId);

        expect($md)->toContain('Social post text')
            ->and($md)->toContain('linkedin')
            ->and($md)->toContain('Blog draft text')
            ->and($md)->toContain('approved')
            ->and($md)->toContain('draft');
    });

    it('shows no content items message when empty', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId, 'Empty Campaign');

        $md = $this->service->exportCampaign($campaignId);

        expect($md)->toContain('Empty Campaign')
            ->and($md)->toContain('No content items yet');
    });

    it('returns not found message for non-existent campaign', function () {
        $md = $this->service->exportCampaign(99999);

        expect($md)->toContain('Campaign Not Found');
    });

    it('includes published_at for published items', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId, 'Published Test');
        $itemId = $this->createContentItem($campaignId, 'Published content', 'published', 'social_post');
        $this->query()->create()->table('content_items')
            ->where('id', '=', $itemId)
            ->update(['published_at' => '2026-05-01 12:00:00']);

        $md = $this->service->exportCampaign($campaignId);

        expect($md)->toContain('Published: 2026-05-01 12:00:00');
    });

    it('does not include other campaigns content', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $campaignId1 = $this->createCampaign($wsId, 'Campaign One');
        $campaignId2 = $this->createCampaign($wsId, 'Campaign Two');
        $this->createContentItem($campaignId1, 'Content one', 'draft');
        $this->createContentItem($campaignId2, 'Content two', 'draft');

        $md = $this->service->exportCampaign($campaignId1);

        expect($md)->toContain('Content one')
            ->and($md)->not->toContain('Content two');
    });
});
