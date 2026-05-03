<?php

declare(strict_types=1);

use App\Dashboard\Http\StreamHttpClient;
use Tests\FakeHttpClient;

describe('StreamHttpClient', function () {
    beforeEach(function () {
        $this->client = new StreamHttpClient();
    });

    it('has SSL verification enabled for GET requests', function () {
        // Verify the source code contains verify_peer => true
        $source = file_get_contents(__DIR__ . '/../../app/dashboard/src/Http/StreamHttpClient.php');

        expect($source)->toContain("'verify_peer' => true")
            ->and($source)->toContain("'verify_peer_name' => true");
    });

    it('has SSL verification enabled for POST requests', function () {
        $source = file_get_contents(__DIR__ . '/../../app/dashboard/src/Http/StreamHttpClient.php');
        $postPos = strpos($source, 'public function post');
        $sslSection = substr($source, $postPos);

        expect($sslSection)->toContain("'verify_peer' => true")
            ->and($sslSection)->toContain("'verify_peer_name' => true");
    });

    it('returns null for unreachable URLs', function () {
        $result = @$this->client->get('http://localhost:59999/nonexistent', [], 1);

        expect($result)->toBeNull();
    });

    it('includes timeout in context', function () {
        $source = file_get_contents(__DIR__ . '/../../app/dashboard/src/Http/StreamHttpClient.php');

        expect($source)->toContain("'timeout' => \$timeout");
    });
});

describe('FakeHttpClient', function () {
    beforeEach(function () {
        $this->client = new FakeHttpClient();
    });

    it('records GET requests', function () {
        $this->client->fake('https://example.com', 200, 'ok');
        $this->client->get('https://example.com', ['X-Test' => '1']);

        expect($this->client->requests())->toHaveCount(1)
            ->and($this->client->requests()[0]['url'])->toBe('https://example.com')
            ->and($this->client->requests()[0]['body'])->toBeNull();
    });

    it('records POST requests with body', function () {
        $this->client->fake('https://api.example.com', 201, '{"id":1}');
        $this->client->post('https://api.example.com', '{"test":true}', ['Content-Type' => 'application/json']);

        expect($this->client->requests())->toHaveCount(1)
            ->and($this->client->requests()[0]['body'])->toBe('{"test":true}');
    });

    it('returns faked response', function () {
        $this->client->fake('https://api.example.com', 200, '{"success":true}');
        $response = $this->client->post('https://api.example.com', '{}');

        expect($response['status'])->toBe(200)
            ->and($response['body'])->toBe('{"success":true}');
    });

    it('returns default 200 response for un-faked URLs', function () {
        $response = $this->client->get('https://unknown.com');

        expect($response['status'])->toBe(200)
            ->and($response['body'])->toBe('{}');
    });

    it('assertRequested passes for made request', function () {
        $this->client->fake('https://example.com', 200, 'ok');
        $this->client->get('https://example.com');

        expect(fn () => $this->client->assertRequested('https://example.com'))
            ->not->toThrow(\AssertionError::class);
    });

    it('assertRequested throws for missing request', function () {
        expect(fn () => $this->client->assertRequested('https://missing.com'))
            ->toThrow(\AssertionError::class);
    });

    it('assertNotRequested passes for missing request', function () {
        expect(fn () => $this->client->assertNotRequested('https://missing.com'))
            ->not->toThrow(\AssertionError::class);
    });

    it('assertNotRequested throws for made request', function () {
        $this->client->fake('https://example.com', 200, 'ok');
        $this->client->get('https://example.com');

        expect(fn () => $this->client->assertNotRequested('https://example.com'))
            ->toThrow(\AssertionError::class);
    });

    it('reset clears all state', function () {
        $this->client->fake('https://example.com', 200, 'ok');
        $this->client->get('https://example.com');

        $this->client->reset();

        expect($this->client->requests())->toBe([]);
    });
});
