<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use Marko\Database\Query\QueryBuilderFactoryInterface;

class VectorSearchService
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    /**
     * Find the top-k most relevant chunks for a given query embedding.
     *
     * @param array<float> $queryEmbedding
     * @return array<int, array<string, mixed>>
     */
    public function search(array $queryEmbedding, int $topK = 5, ?int $workspaceId = null): array
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
            SQL;

        $bindings = [$vectorStr];

        if ($workspaceId !== null) {
            $sql .= ' AND kd.workspace_id = ?';
            $bindings[] = $workspaceId;
        }

        $sql .= ' ORDER BY kc.embedding <=> ? LIMIT ?';
        $bindings[] = $vectorStr;
        $bindings[] = $topK;

        return $this->queryFactory->create()->raw($sql, $bindings);
    }
}
