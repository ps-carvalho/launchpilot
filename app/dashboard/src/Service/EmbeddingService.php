<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

class EmbeddingService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = $_ENV['OPENROUTER_API_KEY'] ?? '';
        $this->model = $_ENV['OPENROUTER_EMBEDDING_MODEL'] ?? 'openai/text-embedding-3-small';
    }

    /**
     * Generate embeddings for an array of text chunks.
     *
     * @param array<int, string> $texts
     * @return array<int, array<float>>|null
     */
    public function embed(array $texts): ?array
    {
        if (empty($this->apiKey) || $this->apiKey === 'sk-or-v1-REPLACE_ME') {
            return null;
        }

        $results = [];

        // OpenRouter embedding API accepts batch requests
        $response = $this->request([
            'model' => $this->model,
            'input' => $texts,
        ]);

        if ($response === null) {
            return null;
        }

        foreach ($response['data'] ?? [] as $item) {
            $results[] = $item['embedding'];
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function request(array $payload): ?array
    {
        $json = json_encode($payload);
        if ($json === false) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                    'HTTP-Referer: https://launchpilot.ai',
                    'X-Title: LaunchPilot',
                ],
                'content' => $json,
                'timeout' => 60,
            ],
        ]);

        $response = @file_get_contents('https://openrouter.ai/api/v1/embeddings', false, $context);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
