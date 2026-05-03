<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

use Marko\Database\Query\QueryBuilderFactoryInterface;

/**
 * Repository for knowledge base queries, including pgvector similarity search.
 * Replaces the shallow VectorSearchService with a deeper, repository-oriented seam.
 */
class KnowledgeBaseRepository
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    /**
     * Find documents in a workspace, optionally limited.
     *
     * @return array<int, array<string, mixed>>
     */
    public function documentsForWorkspace(int $workspaceId, int $limit = 100): array
    {
        return $this->queryFactory->create()->table('knowledge_documents')
            ->where('workspace_id', '=', $workspaceId)
            ->limit($limit)
            ->get();
    }

    /**
     * Find the top-k most similar chunks for a query embedding.
     *
     * @param array<float> $queryEmbedding
     * @return array<int, array<string, mixed>>
     */
    public function findSimilarChunks(array $queryEmbedding, int $workspaceId, int $topK = 5): array
    {
        $vectorStr = '[' . implode(',', $queryEmbedding) . ']';

        $sql = <<<'SQL'
            SELECT
                kc.id,
                kc.document_id,
                kc.chunk_text,
                kc.chunk_index,
                kd.source_url,
                kd.original_name,
                1 - (kc.embedding <=> ?) AS similarity
            FROM knowledge_chunks kc
            JOIN knowledge_documents kd ON kd.id = kc.document_id
            WHERE kc.embedding IS NOT NULL
              AND kd.workspace_id = ?
            ORDER BY kc.embedding <=> ?
            LIMIT ?
            SQL;

        return $this->queryFactory->create()->raw($sql, [
            $vectorStr,
            $workspaceId,
            $vectorStr,
            $topK,
        ]);
    }

    /**
     * Find the first few documents as a fallback when embeddings are unavailable.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fallbackDocuments(int $workspaceId, int $limit = 3): array
    {
        return $this->queryFactory->create()->table('knowledge_documents')
            ->where('workspace_id', '=', $workspaceId)
            ->limit($limit)
            ->get();
    }
}
