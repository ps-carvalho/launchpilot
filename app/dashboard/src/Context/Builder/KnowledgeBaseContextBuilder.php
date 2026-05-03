<?php

declare(strict_types=1);

namespace App\Dashboard\Context\Builder;

use App\Dashboard\Repository\KnowledgeBaseRepository;
use App\Dashboard\Service\EmbeddingService;

/**
 * Builds context from the knowledge base using vector similarity search,
 * falling back to raw document text when embeddings are unavailable.
 */
class KnowledgeBaseContextBuilder implements AgentContextBuilder
{
    public function __construct(
        private readonly EmbeddingService $embedder,
        private readonly KnowledgeBaseRepository $kbRepo,
    ) {}

    public function build(string $message, int $workspaceId, int $userId): array
    {
        $embeddings = $this->embedder->embed([$message]);
        $context = [];

        if ($embeddings !== null && !empty($embeddings[0])) {
            $context = $this->kbRepo->findSimilarChunks($embeddings[0], $workspaceId, 3);
        }

        if (empty($context)) {
            $docs = $this->kbRepo->fallbackDocuments($workspaceId, 3);

            foreach ($docs as $doc) {
                $context[] = [
                    'original_name' => $doc['original_name'],
                    'chunk_text' => substr($doc['raw_text'], 0, 2000),
                ];
            }
        }

        return $context;
    }

    public function supports(string $agentType): bool
    {
        return true;
    }
}
