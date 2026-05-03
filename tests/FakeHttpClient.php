<?php

declare(strict_types=1);

namespace Tests;

use App\Dashboard\Http\HttpClientInterface;

class FakeHttpClient implements HttpClientInterface
{
    /** @var array<string, array{status: int, body: string}> */
    private array $responses = [];

    /** @var list<array{url: string, body: string|null, headers: array}> */
    private array $requests = [];

    public function get(string $url, array $headers = [], int $timeout = 30): ?array
    {
        $this->requests[] = ['url' => $url, 'body' => null, 'headers' => $headers];

        return $this->responses[$url] ?? ['status' => 200, 'body' => '{}'];
    }

    public function post(string $url, string $body, array $headers = [], int $timeout = 30): ?array
    {
        $this->requests[] = ['url' => $url, 'body' => $body, 'headers' => $headers];

        return $this->responses[$url] ?? ['status' => 200, 'body' => '{}'];
    }

    public function fake(string $url, int $status, string $body): void
    {
        $this->responses[$url] = ['status' => $status, 'body' => $body];
    }

    /** @return list<array{url: string, body: string|null, headers: array}> */
    public function requests(): array
    {
        return $this->requests;
    }

    public function assertRequested(string $url): void
    {
        foreach ($this->requests as $req) {
            if ($req['url'] === $url) {
                return;
            }
        }

        throw new \AssertionError("Expected request to {$url} was not made.");
    }

    public function assertNotRequested(string $url): void
    {
        foreach ($this->requests as $req) {
            if ($req['url'] === $url) {
                throw new \AssertionError("Unexpected request to {$url} was made.");
            }
        }
    }

    public function reset(): void
    {
        $this->responses = [];
        $this->requests = [];
    }
}
