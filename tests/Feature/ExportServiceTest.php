<?php

declare(strict_types=1);

use App\Dashboard\Service\ExportService;

beforeEach(function () {
    $this->service = new ExportService($this->query());
});

describe('exportKnowledgeBase', function () {
    it('returns markdown with documents', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $this->createDocument($wsId, 'Document content here', 'readme.txt');

        $md = $this->service->exportKnowledgeBase($wsId);

        expect($md)->toContain('# LaunchPilot Knowledge Base Export')
            ->and($md)->toContain('readme.txt')
            ->and($md)->toContain('Document content here');
    });

    it('includes campaigns and content items', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $campaignId = $this->createCampaign($wsId, 'Summer Launch', 'active');
        $this->query()->create()->table('campaigns')->where('id', '=', $campaignId)->update([
            'description' => 'Our summer campaign',
            'goal' => 'Increase signups',
        ]);
        $this->createContentItem($campaignId, 'Social post content', 'draft', 'social_post', 'linkedin');

        $md = $this->service->exportKnowledgeBase($wsId);

        expect($md)->toContain('Summer Launch')
            ->and($md)->toContain('Our summer campaign')
            ->and($md)->toContain('Increase signups')
            ->and($md)->toContain('Social post content');
    });

    it('shows no content items message when campaign is empty', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $this->createCampaign($wsId, 'Empty Campaign');

        $md = $this->service->exportKnowledgeBase($wsId);

        expect($md)->toContain('Empty Campaign')
            ->and($md)->toContain('No content items yet.');
    });

    it('does not include other workspaces data', function () {
        $userId = $this->createUser();
        $wsId1 = $this->createWorkspace($userId, 'Workspace One');
        $wsId2 = $this->createWorkspace($userId, 'Workspace Two');
        $this->createDocument($wsId1, 'Doc one', 'one.txt');
        $this->createDocument($wsId2, 'Doc two', 'two.txt');

        $md = $this->service->exportKnowledgeBase($wsId1);

        expect($md)->toContain('Doc one')
            ->and($md)->not->toContain('Doc two');
    });

    it('includes source url in document section', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $docId = $this->createDocument($wsId, 'Scraped content', 'website.html');
        $this->query()->create()->table('knowledge_documents')
            ->where('id', '=', $docId)
            ->update(['source_url' => 'https://example.com']);

        $md = $this->service->exportKnowledgeBase($wsId);

        expect($md)->toContain('https://example.com');
    });
});
