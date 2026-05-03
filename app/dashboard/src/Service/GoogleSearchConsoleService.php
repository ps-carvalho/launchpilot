<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Http\HttpClientInterface;

class GoogleSearchConsoleService
{
    private string $clientId;
    private string $clientSecret;

    public function __construct(
        private readonly HttpClientInterface $http,
    ) {
        $this->clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    public function getAuthUrl(string $redirectUri, string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    public function exchangeCode(string $code, string $redirectUri): ?array
    {
        return $this->tokenRequest([
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);
    }

    public function refreshToken(string $refreshToken): ?array
    {
        return $this->tokenRequest([
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function getSearchAnalytics(string $accessToken, string $siteUrl, string $startDate, string $endDate): ?array
    {
        $payload = json_encode([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => ['query'],
            'rowLimit' => 50,
        ]);

        $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($siteUrl) . '/searchAnalytics/query';

        $response = $this->http->post($url, $payload, [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ], 30);

        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response['body'], true);
        return is_array($decoded) ? ($decoded['rows'] ?? []) : null;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function listSites(string $accessToken): ?array
    {
        $response = $this->http->get(
            'https://www.googleapis.com/webmasters/v3/sites',
            ['Authorization' => 'Bearer ' . $accessToken],
            30
        );

        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response['body'], true);
        return is_array($decoded) ? ($decoded['siteEntry'] ?? []) : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function tokenRequest(array $data): ?array
    {
        $response = $this->http->post(
            'https://oauth2.googleapis.com/token',
            http_build_query($data),
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            30
        );

        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response['body'], true);
        return is_array($decoded) ? $decoded : null;
    }
}
