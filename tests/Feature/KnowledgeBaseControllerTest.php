<?php

declare(strict_types=1);

use App\Dashboard\Controller\KnowledgeBaseController;
use App\Dashboard\Service\EmbeddingService;
use Tests\FakeHttpClient;

beforeEach(function () {
    $this->controller = $this->container()->get(KnowledgeBaseController::class);
    $this->originalFiles = $_FILES;
});

afterEach(function () {
    $_FILES = $this->originalFiles;
    if (!empty($this->tempFiles)) {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
});

describe('index', function () {
    it('returns 200 with documents', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $this->createDocument($wsId, 'Doc content', 'doc.txt');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->index($request);

        expect($response->statusCode())->toBe(200);
    });

    it('shows empty state when no documents', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->index($request);
        $body = $response->body();

        expect($response->statusCode())->toBe(200)
            ->and($body)->toContain('documents')
            ->and($body)->toContain('"documents":[]');
    });

    it('returns empty when user has no workspace', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->index($request);

        expect($response->statusCode())->toBe(200);
    });
});

describe('show', function () {
    it('returns 200 for owned document', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $docId = $this->createDocument($wsId, 'Content', 'doc.txt');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->show($request, $docId);

        expect($response->statusCode())->toBe(200);
    });

    it('redirects for unauthorized document', function () {
        $userId1 = $this->createUser('User One', 'one@test.com');
        $userId2 = $this->createUser('User Two', 'two@test.com');
        $this->loginAsUser($userId2);
        $wsId = $this->createWorkspace($userId1);
        $docId = $this->createDocument($wsId, 'Content', 'doc.txt');

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->show($request, $docId);

        expect($response->statusCode())->toBe(302);
    });
});

describe('upload', function () {
    it('creates document from uploaded file', function () {
        $http = new FakeHttpClient();
        $http->fake('https://openrouter.ai/api/v1/embeddings', 200, json_encode([
            'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
        ]));
        $this->container()->instance(\App\Dashboard\Http\HttpClientInterface::class, $http);
        // Rebuild controller with new http client
        $this->controller = $this->container()->get(KnowledgeBaseController::class);

        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);

        $tmpFile = tempnam(sys_get_temp_dir(), 'upload_');
        $this->tempFiles[] = $tmpFile;
        file_put_contents($tmpFile, 'Uploaded file content');

        $_FILES['document'] = [
            'tmp_name' => $tmpFile,
            'name' => 'upload.txt',
            'type' => 'text/plain',
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->upload($request);

        expect($response->statusCode())->toBe(302);

        $doc = $this->query()->create()->table('knowledge_documents')
            ->where('workspace_id', '=', $wsId)
            ->first();
        expect($doc['original_name'])->toBe('upload.txt');
    });

    it('rejects unsupported file type', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $tmpFile = tempnam(sys_get_temp_dir(), 'upload_');
        $this->tempFiles[] = $tmpFile;
        file_put_contents($tmpFile, 'binary data');

        $_FILES['document'] = [
            'tmp_name' => $tmpFile,
            'name' => 'file.exe',
            'type' => 'application/x-msdownload',
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->upload($request);

        expect($response->statusCode())->toBe(302);

        $session = $this->container()->get(\Marko\Session\Contracts\SessionInterface::class);
        $flash = $session->flash()->all();
        expect($flash['error'] ?? [])->toContain('Unsupported file type. Please upload TXT, MD, PDF, or DOCX files.');
    });

    it('fails when no workspace exists', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);

        $tmpFile = tempnam(sys_get_temp_dir(), 'upload_');
        $this->tempFiles[] = $tmpFile;
        file_put_contents($tmpFile, 'content');

        $_FILES['document'] = [
            'tmp_name' => $tmpFile,
            'name' => 'file.txt',
            'type' => 'text/plain',
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->upload($request);

        expect($response->statusCode())->toBe(302);

        $session = $this->container()->get(\Marko\Session\Contracts\SessionInterface::class);
        $flash = $session->flash()->all();
        expect($flash['error'] ?? [])->toContain('No workspace found.');
    });
});

describe('scrape', function () {
    it('creates a document from a scraped URL', function () {
        $http = new FakeHttpClient();
        $html = '<html><head><title>Test Site</title><meta name="description" content="A test description"></head><body><h1>Welcome</h1><p>This is the body content.</p></body></html>';
        $http->fake('https://example.com', 200, $html);
        $this->container()->instance(\App\Dashboard\Http\HttpClientInterface::class, $http);
        $this->controller = $this->container()->get(KnowledgeBaseController::class);

        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['url' => 'https://example.com'],
            body: '',
        );

        $response = $this->controller->scrape($request);

        expect($response->statusCode())->toBe(302);

        $doc = $this->query()->create()->table('knowledge_documents')
            ->where('workspace_id', '=', $wsId)
            ->first();

        expect($doc['source_url'])->toBe('https://example.com');
        expect($doc['original_name'])->toBe('example.com');
        expect($doc['raw_text'])->toContain('Test Site');
    });

    it('rejects invalid URL', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['url' => 'not-a-url'],
            body: '',
        );

        $response = $this->controller->scrape($request);

        expect($response->statusCode())->toBe(302);

        $session = $this->container()->get(\Marko\Session\Contracts\SessionInterface::class);
        $flash = $session->flash()->all();
        expect($flash['error'] ?? [])->toContain('Please enter a valid URL.');
    });

    it('fails when scrape returns empty content', function () {
        $http = new FakeHttpClient();
        $http->fake('https://example.com', 200, '<html><head></head><body></body></html>');
        $this->container()->instance(\App\Dashboard\Http\HttpClientInterface::class, $http);
        $this->controller = $this->container()->get(KnowledgeBaseController::class);

        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['url' => 'https://example.com'],
            body: '',
        );

        $response = $this->controller->scrape($request);

        expect($response->statusCode())->toBe(302);

        $session = $this->container()->get(\Marko\Session\Contracts\SessionInterface::class);
        $flash = $session->flash()->all();
        expect($flash['error'] ?? [])->toContain('No content found at that URL.');
    });

    it('fails when no workspace exists', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['url' => 'https://example.com'],
            body: '',
        );

        $response = $this->controller->scrape($request);

        expect($response->statusCode())->toBe(302);

        $session = $this->container()->get(\Marko\Session\Contracts\SessionInterface::class);
        $flash = $session->flash()->all();
        expect($flash['error'] ?? [])->toContain('No workspace found.');
    });
});

describe('delete', function () {
    it('removes document and its chunks', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $docId = $this->createDocument($wsId, 'Content', 'doc.txt');
        $this->query()->create()->table('knowledge_chunks')->insert([
            'document_id' => $docId,
            'chunk_text' => 'Chunk',
            'embedding' => null,
            'chunk_index' => 0,
        ]);

        $response = $this->controller->delete($docId);

        expect($response->statusCode())->toBe(302);

        $doc = $this->query()->create()->table('knowledge_documents')
            ->where('id', '=', $docId)
            ->first();
        expect($doc)->toBeNull();

        $chunks = $this->query()->create()->table('knowledge_chunks')
            ->where('document_id', '=', $docId)
            ->get();
        expect($chunks)->toBe([]);
    });

    it('redirects for unauthorized document', function () {
        $userId1 = $this->createUser('User One', 'one@test.com');
        $userId2 = $this->createUser('User Two', 'two@test.com');
        $this->loginAsUser($userId2);
        $wsId = $this->createWorkspace($userId1);
        $docId = $this->createDocument($wsId, 'Content', 'doc.txt');

        $response = $this->controller->delete($docId);

        expect($response->statusCode())->toBe(302);
    });
});

describe('search', function () {
    it('returns results when embeddings are available', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $docId = $this->createDocument($wsId, 'Content', 'doc.txt');

        $vector = array_fill(0, 1536, 0.0);
        $vector[0] = 1.0;

        $this->query()->create()->table('knowledge_chunks')->insert([
            'document_id' => $docId,
            'chunk_text' => 'Test chunk',
            'embedding' => '[' . implode(',', $vector) . ']',
            'chunk_index' => 0,
        ]);

        // Mock embedder to return the same vector
        $fakeEmbedder = new class ($vector) extends EmbeddingService {
            private array $fixedVector;
            public function __construct(array $vector)
            {
                // Skip parent constructor
                $this->fixedVector = $vector;
            }
            public function embed(array $texts): ?array
            {
                return [$this->fixedVector];
            }
        };
        $this->container()->instance(EmbeddingService::class, $fakeEmbedder);
        $this->controller = $this->container()->get(KnowledgeBaseController::class);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: ['q' => 'test query'],
            post: [],
            body: '',
        );

        $response = $this->controller->search($request);
        $body = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(200)
            ->and($body['results'])->toHaveCount(1)
            ->and($body['results'][0]['chunk_text'])->toBe('Test chunk');
    });

    it('returns error when embeddings are unavailable', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $fakeEmbedder = new class extends EmbeddingService {
            public function __construct() { /* skip parent */ }
            public function embed(array $texts): ?array { return null; }
        };
        $this->container()->instance(EmbeddingService::class, $fakeEmbedder);
        $this->controller = $this->container()->get(KnowledgeBaseController::class);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: ['q' => 'test query'],
            post: [],
            body: '',
        );

        $response = $this->controller->search($request);
        $body = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(200)
            ->and($body['error'])->toContain('Embeddings unavailable')
            ->and($body['results'])->toBe([]);
    });

    it('returns 422 for empty query', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: ['q' => ''],
            post: [],
            body: '',
        );

        $response = $this->controller->search($request);
        $body = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(422)
            ->and($body['errors']['q'])->toContain('The q field is required.');
    });

    it('returns 404 when no workspace exists', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: ['q' => 'test'],
            post: [],
            body: '',
        );

        $response = $this->controller->search($request);
        $body = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(404)
            ->and($body['error'])->toContain('No workspace found');
    });
});
