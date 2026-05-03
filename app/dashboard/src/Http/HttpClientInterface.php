<?php

declare(strict_types=1);

namespace App\Dashboard\Http;

interface HttpClientInterface
{
    /**
     * Send a GET request.
     *
     * @param array<string, string> $headers
     * @return array{status: int, body: string}|null
     */
    public function get(string $url, array $headers = [], int $timeout = 30): ?array;

    /**
     * Send a POST request.
     *
     * @param array<string, string> $headers
     * @return array{status: int, body: string}|null
     */
    public function post(string $url, string $body, array $headers = [], int $timeout = 30): ?array;
}
