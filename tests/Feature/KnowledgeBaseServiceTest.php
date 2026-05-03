<?php

declare(strict_types=1);

use App\Dashboard\Service\DocumentParser;
use App\Dashboard\Service\EmbeddingService;
use App\Dashboard\Service\KnowledgeBaseService;
use App\Dashboard\Service\TextChunker;
use Tests\FakeHttpClient;

beforeEach(function () {
    $this->http = new FakeHttpClient();
    $this->parser = new DocumentParser();
    $this->chunker = new TextChunker();
    $this->embedder = new EmbeddingService($this->http);
    $this->service = new KnowledgeBaseService(
        $this->query(),
        $this->parser,
        $this->chunker,
        $this->embedder,
    );
    $this->originalApiKey = $_ENV['OPENROUTER_API_KEY'] ?? null;
    $_ENV['OPENROUTER_API_KEY'] = 'test-api-key';
});

afterEach(function () {
    $this->http->reset();
    if ($this->originalApiKey !== null) {
        $_ENV['OPENROUTER_API_KEY'] = $this->originalApiKey;
    } else {
        unset($_ENV['OPENROUTER_API_KEY']);
    }
    // Clean up temp files
    if (!empty($this->tempFiles)) {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
});

describe('processUpload', function () {
    it('creates document and chunks for txt file', function () {
        $this->http->fake('https://openrouter.ai/api/v1/embeddings', 200, json_encode([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.1)],
            ],
        ]));

        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $tmpFile = tempnam(sys_get_temp_dir(), 'txt_');
        $this->tempFiles[] = $tmpFile;
        file_put_contents($tmpFile, 'Hello world this is a test document.');

        $result = $this->service->processUpload($tmpFile, 'test.txt', 'text/plain', $wsId);

        expect($result)->not->toBeNull()
            ->and($result['chunks'])->toBe(1);

        $doc = $this->query()->create()->table('knowledge_documents')
            ->where('workspace_id', '=', $wsId)
            ->first();
        expect($doc['original_name'])->toBe('test.txt');

        $chunks = $this->query()->create()->table('knowledge_chunks')
            ->where('document_id', '=', $doc['id'])
            ->get();
        expect(count($chunks))->toBe(1);
    });

    it('returns null for empty file', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $tmpFile = tempnam(sys_get_temp_dir(), 'txt_');
        $this->tempFiles[] = $tmpFile;
        file_put_contents($tmpFile, '');

        $result = $this->service->processUpload($tmpFile, 'empty.txt', 'text/plain', $wsId);

        expect($result)->toBeNull();
    });

    it('returns null for unsupported file type', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $tmpFile = tempnam(sys_get_temp_dir(), 'bin_');
        $this->tempFiles[] = $tmpFile;
        file_put_contents($tmpFile, 'binary data');

        $result = $this->service->processUpload($tmpFile, 'file.bin', 'application/octet-stream', $wsId);

        expect($result)->toBeNull();
    });

    it('creates multiple chunks for long text', function () {
        $this->http->fake('https://openrouter.ai/api/v1/embeddings', 200, json_encode([
            'data' => array_fill(0, 5, ['embedding' => array_fill(0, 1536, 0.1)]),
        ]));

        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $tmpFile = tempnam(sys_get_temp_dir(), 'txt_');
        $this->tempFiles[] = $tmpFile;
        // Create text long enough to split into multiple chunks (chunk size 2000, overlap 200)
        file_put_contents($tmpFile, str_repeat('word ', 800));

        $result = $this->service->processUpload($tmpFile, 'long.txt', 'text/plain', $wsId);

        expect($result)->not->toBeNull()
            ->and($result['chunks'])->toBeGreaterThan(1);
    });
});

describe('processScrapedDocument', function () {
    it('creates chunks for existing document', function () {
        $this->http->fake('https://openrouter.ai/api/v1/embeddings', 200, json_encode([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.1)],
            ],
        ]));

        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $docId = $this->createDocument($wsId, 'Scraped website content here', 'example.com');

        $result = $this->service->processScrapedDocument($docId);

        expect($result)->not->toBeNull()
            ->and($result['chunks'])->toBe(1);

        $chunks = $this->query()->create()->table('knowledge_chunks')
            ->where('document_id', '=', $docId)
            ->get();
        expect(count($chunks))->toBe(1);
    });

    it('replaces existing chunks when reprocessing', function () {
        $this->http->fake('https://openrouter.ai/api/v1/embeddings', 200, json_encode([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.1)],
            ],
        ]));

        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $docId = $this->createDocument($wsId, 'New content', 'example.com');

        // Insert an old chunk
        $this->query()->create()->table('knowledge_chunks')->insert([
            'document_id' => $docId,
            'chunk_text' => 'Old chunk',
            'embedding' => null,
            'chunk_index' => 0,
        ]);

        $result = $this->service->processScrapedDocument($docId);

        $chunks = $this->query()->create()->table('knowledge_chunks')
            ->where('document_id', '=', $docId)
            ->get();
        expect(count($chunks))->toBe(1)
            ->and($chunks[0]['chunk_text'])->not->toBe('Old chunk');
    });

    it('returns null for non-existent document', function () {
        $result = $this->service->processScrapedDocument(99999);

        expect($result)->toBeNull();
    });

    it('returns null for document with empty text', function () {
        $userId = $this->createUser();
        $wsId = $this->createWorkspace($userId);
        $docId = $this->createDocument($wsId, '', 'empty.com');

        $result = $this->service->processScrapedDocument($docId);

        expect($result)->toBeNull();
    });
});
