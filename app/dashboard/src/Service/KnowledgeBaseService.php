<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use Marko\Database\Query\QueryBuilderFactoryInterface;

class KnowledgeBaseService
{
    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly DocumentParser $parser,
        private readonly TextChunker $chunker,
        private readonly EmbeddingService $embedder,
    ) {}

    /**
     * Parse and create a document record without chunking/embedding.
     * Use processDocumentEmbedding() or dispatch a queue job for the slow part.
     *
     * @return int|null Document ID
     */
    public function createDocument(
        string $filePath,
        string $originalName,
        string $mimeType,
        int $workspaceId,
    ): ?int {
        $rawText = $this->parser->parse($filePath, $mimeType);

        if ($rawText === null || trim($rawText) === '') {
            return null;
        }

        return $this->queryFactory->create()->table('knowledge_documents')->insert([
            'workspace_id' => $workspaceId,
            'filename' => basename($filePath),
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'raw_text' => $rawText,
            'metadata' => json_encode([
                'source' => 'upload',
                'parsed_at' => date('c'),
            ]),
        ]);
    }

    /**
     * Process an uploaded file: parse, chunk, embed, and store.
     * Synchronous — blocks the request thread. Prefer createDocument + queue job.
     *
     * @return array{id: int, chunks: int}|null
     */
    public function processUpload(
        string $filePath,
        string $originalName,
        string $mimeType,
        int $workspaceId,
    ): ?array {
        $documentId = $this->createDocument($filePath, $originalName, $mimeType, $workspaceId);

        if ($documentId === null) {
            return null;
        }

        return $this->processDocumentEmbedding($documentId);
    }

    /**
     * Process website scrape results: chunk and embed.
     *
     * @return array{id: int, chunks: int}|null
     */
    public function processScrapedDocument(int $documentId): ?array
    {
        return $this->processDocumentEmbedding($documentId);
    }

    /**
     * Chunk and embed an existing document by ID.
     * Used by queue workers for async processing.
     *
     * @return array{id: int, chunks: int}|null
     */
    public function processDocumentEmbedding(int $documentId): ?array
    {
        $doc = $this->queryFactory->create()->table('knowledge_documents')
            ->where('id', '=', $documentId)
            ->first();

        if ($doc === null || empty($doc['raw_text'])) {
            return null;
        }

        // Delete existing chunks if any
        $this->queryFactory->create()->table('knowledge_chunks')
            ->where('document_id', '=', $documentId)
            ->delete();

        $chunks = $this->chunker->chunk($doc['raw_text'], 2000, 200);
        $this->storeChunks($documentId, $chunks);

        return [
            'id' => $documentId,
            'chunks' => count($chunks),
        ];
    }

    /**
     * @param array<int, string> $chunks
     */
    private function storeChunks(int $documentId, array $chunks): void
    {
        if (empty($chunks)) {
            return;
        }

        $embeddings = $this->embedder->embed($chunks);

        foreach ($chunks as $index => $chunkText) {
            $embedding = $embeddings[$index] ?? null;

            $this->queryFactory->create()->table('knowledge_chunks')->insert([
                'document_id' => $documentId,
                'chunk_text' => $chunkText,
                'embedding' => $embedding !== null ? $this->vectorToString($embedding) : null,
                'chunk_index' => $index,
            ]);
        }
    }

    /**
     * @param array<float> $vector
     */
    private function vectorToString(array $vector): string
    {
        return '[' . implode(',', $vector) . ']';
    }
}
