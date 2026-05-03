<?php

declare(strict_types=1);

use App\Dashboard\Service\VectorSearchService;

beforeEach(function () {
    $this->service = new VectorSearchService($this->query());
});

describe('search', function () {
    it('returns chunks ordered by similarity', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $docId = $this->createDocument($wsId, ' irrelevant ', 'doc.txt');

        // Insert chunks with embeddings (1536 dims)
        $vector1 = array_fill(0, 1536, 0.0);
        $vector1[0] = 1.0;
        $vector2 = array_fill(0, 1536, 0.0);
        $vector2[1] = 1.0;

        $this->query()->create()->table('knowledge_chunks')->insert([
            'document_id' => $docId,
            'chunk_text' => 'First chunk about apples',
            'embedding' => '[' . implode(',', $vector1) . ']',
            'chunk_index' => 0,
        ]);
        $this->query()->create()->table('knowledge_chunks')->insert([
            'document_id' => $docId,
            'chunk_text' => 'Second chunk about bananas',
            'embedding' => '[' . implode(',', $vector2) . ']',
            'chunk_index' => 1,
        ]);

        // Query vector matching first chunk
        $queryVector = array_fill(0, 1536, 0.0);
        $queryVector[0] = 1.0;

        $results = $this->service->search($queryVector, 5, $wsId);

        expect($results)->toHaveCount(2)
            ->and($results[0]['chunk_text'])->toBe('First chunk about apples')
            ->and((float) $results[0]['similarity'])->toBeGreaterThan((float) $results[1]['similarity']);
    });

    it('filters by workspace_id', function () {
        $userId = $this->createUser();
        $wsId1 = $this->createWorkspace($userId, 'WS One');
        $wsId2 = $this->createWorkspace($userId, 'WS Two');
        $docId1 = $this->createDocument($wsId1, ' content ', 'doc1.txt');
        $docId2 = $this->createDocument($wsId2, ' content ', 'doc2.txt');

        $vector = array_fill(0, 1536, 0.0);
        $vector[0] = 1.0;

        $this->query()->create()->table('knowledge_chunks')->insert([
            'document_id' => $docId1,
            'chunk_text' => 'Workspace one chunk',
            'embedding' => '[' . implode(',', $vector) . ']',
            'chunk_index' => 0,
        ]);
        $this->query()->create()->table('knowledge_chunks')->insert([
            'document_id' => $docId2,
            'chunk_text' => 'Workspace two chunk',
            'embedding' => '[' . implode(',', $vector) . ']',
            'chunk_index' => 0,
        ]);

        $results = $this->service->search($vector, 5, $wsId1);

        expect($results)->toHaveCount(1)
            ->and($results[0]['chunk_text'])->toBe('Workspace one chunk');
    });

    it('returns empty array when no matching chunks', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);

        $vector = array_fill(0, 1536, 0.0);
        $vector[0] = 1.0;

        $results = $this->service->search($vector, 5, $wsId);

        expect($results)->toBe([]);
    });

    it('excludes chunks with null embeddings', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $docId = $this->createDocument($wsId, ' content ', 'doc.txt');

        $this->query()->create()->table('knowledge_chunks')->insert([
            'document_id' => $docId,
            'chunk_text' => 'No embedding chunk',
            'embedding' => null,
            'chunk_index' => 0,
        ]);

        $vector = array_fill(0, 1536, 0.0);
        $vector[0] = 1.0;

        $results = $this->service->search($vector, 5, $wsId);

        expect($results)->toBe([]);
    });

    it('respects top_k limit', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $docId = $this->createDocument($wsId, ' content ', 'doc.txt');

        $vector = array_fill(0, 1536, 0.0);
        $vector[0] = 1.0;

        for ($i = 0; $i < 5; $i++) {
            $this->query()->create()->table('knowledge_chunks')->insert([
                'document_id' => $docId,
                'chunk_text' => "Chunk {$i}",
                'embedding' => '[' . implode(',', $vector) . ']',
                'chunk_index' => $i,
            ]);
        }

        $results = $this->service->search($vector, 2, $wsId);

        expect($results)->toHaveCount(2);
    });
});
