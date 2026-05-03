<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Http\HttpClientInterface;

class EmbeddingService
{
    private string $apiKey;
    private string $model;

    public function __construct(
        private readonly HttpClientInterface $http,
    ) {
        $this->apiKey = getenv('OPENROUTER_API_KEY') ?: '';
        $this->model = $_ENV['OPENROUTER_EMBEDDING_MODEL'] ?? 'openai/text-embedding-3-small';
    }

    /**
     * @param array<int, string> $texts
     * @return array<int, array<float>>|null
     */
    public function embed(array $texts): ?array
    {
        if (empty($this->apiKey) || $this->apiKey === 'sk-or-v1-REPLACE_ME') {
            return null;
        }

        $response = $this->http->post(
            'https://openrouter.ai/api/v1/embeddings',
            json_encode([
                'model' => $this->model,
                'input' => $texts,
            ]),
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
                'HTTP-Referer' => 'https://launchpilot.ai',
                'X-Title' => 'LaunchPilot',
            ],
            60
        );

        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return null;
        }

        $results = [];
        foreach ($decoded['data'] ?? [] as $item) {
            $results[] = $item['embedding'];
        }

        return $results;
    }
}
