<?php

declare(strict_types=1);

use App\Dashboard\Service\EmbeddingService;
use Tests\FakeHttpClient;

beforeEach(function () {
    $this->http = new FakeHttpClient();
    $this->originalApiKey = getenv('OPENROUTER_API_KEY') ?: null;
    putenv('OPENROUTER_API_KEY=test-api-key');
    $this->service = new EmbeddingService($this->http);
});

afterEach(function () {
    $this->http->reset();
    if ($this->originalApiKey !== null) {
        putenv('OPENROUTER_API_KEY=' . $this->originalApiKey);
    } else {
        putenv('OPENROUTER_API_KEY');
    }
});

describe('embed', function () {
    it('returns embeddings for valid API key', function () {
        $this->http->fake('https://openrouter.ai/api/v1/embeddings', 200, json_encode([
            'data' => [
                ['embedding' => [0.1, 0.2, 0.3]],
                ['embedding' => [0.4, 0.5, 0.6]],
            ],
        ]));

        $result = $this->service->embed(['First text', 'Second text']);

        expect($result)->toHaveCount(2)
            ->and($result[0])->toBe([0.1, 0.2, 0.3])
            ->and($result[1])->toBe([0.4, 0.5, 0.6]);
    });

    it('sends correct request body and headers', function () {
        $this->http->fake('https://openrouter.ai/api/v1/embeddings', 200, json_encode([
            'data' => [['embedding' => [0.1]]],
        ]));

        $this->service->embed(['Hello world']);

        $this->http->assertRequested('https://openrouter.ai/api/v1/embeddings');
        $requests = $this->http->requests();
        $req = $requests[0];

        $body = json_decode($req['body'], true);
        expect($body['model'])->toBe('openai/text-embedding-3-small')
            ->and($body['input'])->toBe(['Hello world'])
            ->and($req['headers']['Authorization'])->toBe('Bearer test-api-key');
    });

    it('returns null when API key is missing', function () {
        putenv('OPENROUTER_API_KEY=');
        $service = new EmbeddingService($this->http);
        $result = $service->embed(['Hello']);

        expect($result)->toBeNull();
        $this->http->assertNotRequested('https://openrouter.ai/api/v1/embeddings');
    });

    it('returns null when API key is placeholder', function () {
        putenv('OPENROUTER_API_KEY=sk-or-v1-REPLACE_ME');
        $service = new EmbeddingService($this->http);
        $result = $service->embed(['Hello']);

        expect($result)->toBeNull();
        $this->http->assertNotRequested('https://openrouter.ai/api/v1/embeddings');
    });

    it('returns null on HTTP failure', function () {
        $this->http->fake('https://openrouter.ai/api/v1/embeddings', 500, 'Internal Server Error');

        $result = $this->service->embed(['Hello']);

        expect($result)->toBeNull();
    });

    it('returns null for malformed JSON response', function () {
        $this->http->fake('https://openrouter.ai/api/v1/embeddings', 200, 'not json');

        $result = $this->service->embed(['Hello']);

        expect($result)->toBeNull();
    });

    it('uses custom model from env', function () {
        $_ENV['OPENROUTER_EMBEDDING_MODEL'] = 'custom/model';
        $this->http->fake('https://openrouter.ai/api/v1/embeddings', 200, json_encode([
            'data' => [['embedding' => [0.1]]],
        ]));

        $service = new EmbeddingService($this->http);
        $service->embed(['Hello']);

        $requests = $this->http->requests();
        $body = json_decode($requests[0]['body'], true);
        expect($body['model'])->toBe('custom/model');

        unset($_ENV['OPENROUTER_EMBEDDING_MODEL']);
    });
});
