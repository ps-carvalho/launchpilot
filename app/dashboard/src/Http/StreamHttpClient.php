<?php

declare(strict_types=1);

namespace App\Dashboard\Http;

class StreamHttpClient implements HttpClientInterface
{
    public function get(string $url, array $headers = [], int $timeout = 30): ?array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "$name: $value";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $headerLines,
                'timeout' => $timeout,
                'follow_location' => true,
                'max_redirects' => 3,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            return null;
        }

        $status = 200;
        $headers = http_get_last_response_headers() ?: [];
        foreach ($headers as $header) {
            if (str_starts_with($header, 'HTTP/')) {
                $parts = explode(' ', $header);
                $status = (int) ($parts[1] ?? 200);
                break;
            }
        }

        return ['status' => $status, 'body' => $body];
    }

    public function post(string $url, string $body, array $headers = [], int $timeout = 30): ?array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "$name: $value";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerLines,
                'content' => $body,
                'timeout' => $timeout,
                'follow_location' => true,
                'max_redirects' => 3,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $status = 200;
        $headers = http_get_last_response_headers() ?: [];
        foreach ($headers as $header) {
            if (str_starts_with($header, 'HTTP/')) {
                $parts = explode(' ', $header);
                $status = (int) ($parts[1] ?? 200);
                break;
            }
        }

        return ['status' => $status, 'body' => $response];
    }
}
