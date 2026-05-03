<?php

declare(strict_types=1);

namespace App\Dashboard\Flow;

use App\Dashboard\Authorization\WorkspaceAuthorization;
use App\Dashboard\Service\KnowledgeBaseService;
use App\Dashboard\Service\WebsiteScraper;
use Marko\Database\Query\QueryBuilderFactoryInterface;

/**
 * Orchestrates the onboarding flow: scrape → validate → store → process.
 * Extracted from OnboardingController to separate business rules from HTTP adaptation.
 */
class OnboardingFlow
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly WebsiteScraper $scraper,
        private readonly KnowledgeBaseService $kbService,
        private readonly WorkspaceAuthorization $workspaceAuth,
    ) {}

    /**
     * Complete onboarding by scraping a URL and adding it to the knowledge base.
     *
     * @return array{success: bool, error?: string}
     */
    public function complete(int $userId, string $url): array
    {
        $data = $this->scraper->scrape($url);

        if ($data === null) {
            return ['success' => false, 'error' => 'Could not scrape that URL. Please check the address and try again.'];
        }

        $workspace = $this->workspaceAuth->firstWorkspaceFor($userId);

        if ($workspace === null) {
            return ['success' => false, 'error' => 'No workspace found.'];
        }

        $rawText = implode("\n\n", array_filter([
            $data['title'] ? "Title: {$data['title']}" : null,
            $data['description'] ? "Description: {$data['description']}" : null,
            $data['body'] ? "Content:\n{$data['body']}" : null,
        ]));

        $documentId = $this->queryFactory->create()->table('knowledge_documents')->insert([
            'workspace_id' => $workspace['id'],
            'source_url' => $url,
            'original_name' => parse_url($url, PHP_URL_HOST) ?: $url,
            'raw_text' => $rawText,
            'metadata' => json_encode([
                'title' => $data['title'],
                'description' => $data['description'],
                'source' => 'website_scrape',
            ]),
        ]);

        $this->kbService->processScrapedDocument($documentId);

        return ['success' => true];
    }
}
