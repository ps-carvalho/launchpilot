<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Http\HttpClientInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Log\Contracts\LoggerInterface;

/**
 * Handles async video generation via OpenRouter's /api/v1/videos API.
 * Workflow: submit → poll → download → store locally.
 */
class VideoGenerationService
{
    public function __construct(
        private readonly ApiKeyResolver $apiKeyResolver,
        private readonly HttpClientInterface $http,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Submit a video generation job to OpenRouter.
     *
     * @return array{job_id: string, status: string, poll_url: string}|null
     */
    public function submit(
        int $userId,
        string $prompt,
        array $modelConfig = [],
        ?string $aspectRatio = null,
        ?string $resolution = null,
        ?int $duration = null,
    ): ?array {
        $apiKey = $this->apiKeyResolver->resolve($userId);

        if (empty($apiKey)) {
            $this->logger->warning('Video generation API key not resolved', ['user_id' => $userId]);
            return null;
        }

        $model = $modelConfig['model'] ?? 'google/veo-3.1-lite';

        $this->logger->info('Video generation submit', [
            'user_id' => $userId,
            'model' => $model,
        ]);

        $body = [
            'model' => $model,
            'prompt' => $prompt,
        ];

        if ($aspectRatio !== null) {
            $body['aspect_ratio'] = $aspectRatio;
        }
        if ($resolution !== null) {
            $body['resolution'] = $resolution;
        }
        if ($duration !== null) {
            $body['duration'] = $duration;
        }

        $response = $this->http->post(
            'https://openrouter.ai/api/v1/videos',
            json_encode($body),
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
                'HTTP-Referer' => 'https://launchpilot.ai',
                'X-Title' => 'LaunchPilot',
            ],
            30
        );

        if ($response === null) {
            $this->logger->error('Video generation submit failed', ['user_id' => $userId, 'model' => $model]);
            return null;
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded) || empty($decoded['id'])) {
            $this->logger->error('Video generation submit decode failed', [
                'user_id' => $userId,
                'body_preview' => substr($response['body'] ?? '', 0, 200),
            ]);
            return null;
        }

        $this->logger->info('Video generation submitted', [
            'user_id' => $userId,
            'job_id' => $decoded['id'],
            'model' => $model,
        ]);

        return [
            'job_id' => $decoded['id'],
            'status' => $decoded['status'] ?? 'submitted',
            'poll_url' => $decoded['polling_url'] ?? null,
        ];
    }

    /**
     * Poll a video job status.
     *
     * @return array{status: string, unsigned_urls: array<int, string>}|null
     */
    public function poll(string $jobId, int $userId): ?array
    {
        $apiKey = $this->apiKeyResolver->resolve($userId);

        if (empty($apiKey)) {
            $this->logger->warning('Video poll API key not resolved', ['user_id' => $userId, 'job_id' => $jobId]);
            return null;
        }

        $this->logger->debug('Video generation poll', ['user_id' => $userId, 'job_id' => $jobId]);

        $response = $this->http->get(
            'https://openrouter.ai/api/v1/videos/' . $jobId,
            [
                'Authorization' => 'Bearer ' . $apiKey,
                'HTTP-Referer' => 'https://launchpilot.ai',
                'X-Title' => 'LaunchPilot',
            ],
            30
        );

        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            $this->logger->error('Video poll decode failed', [
                'job_id' => $jobId,
                'body_preview' => substr($response['body'] ?? '', 0, 200),
            ]);
            return null;
        }

        $status = $decoded['status'] ?? 'unknown';
        $this->logger->debug('Video poll result', ['job_id' => $jobId, 'status' => $status]);

        return [
            'status' => $status,
            'unsigned_urls' => $decoded['unsigned_urls'] ?? [],
        ];
    }

    /**
     * Download a video from OpenRouter and save it locally.
     *
     * @return string|null Local file path
     */
    public function download(string $url, string $campaignPath, string $filename, int $userId): ?string
    {
        $apiKey = $this->apiKeyResolver->resolve($userId);

        if (empty($apiKey)) {
            $this->logger->warning('Video download API key not resolved', ['user_id' => $userId]);
            return null;
        }

        $dir = '/var/www/storage/media/' . $campaignPath;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $localPath = $dir . '/' . $filename;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: https://launchpilot.ai',
            'X-Title: LaunchPilot',
        ]);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data === false || $httpCode !== 200) {
            $this->logger->error('Video download failed', [
                'http_code' => $httpCode,
                'url' => $url,
            ]);
            return null;
        }

        file_put_contents($localPath, $data);

        $this->logger->info('Video downloaded', [
            'local_path' => $localPath,
            'size_bytes' => strlen($data),
        ]);

        return $localPath;
    }

    /**
     * Create a media_asset record for a video job.
     */
    public function recordJob(int $campaignId, string $jobId, string $model, string $prompt, array $options = []): int
    {
        return $this->queryFactory->create()->table('media_assets')->insert([
            'campaign_id' => $campaignId,
            'type' => 'video',
            'source_url' => null,
            'local_path' => null,
            'status' => 'pending',
            'metadata' => json_encode(array_merge([
                'job_id' => $jobId,
                'model' => $model,
                'prompt' => $prompt,
            ], $options)),
        ]);
    }

    /**
     * Update a media_asset record when the video is ready.
     */
    public function markReady(int $assetId, string $localPath, ?string $sourceUrl = null): void
    {
        $this->queryFactory->create()->table('media_assets')
            ->where('id', '=', $assetId)
            ->update([
                'local_path' => $localPath,
                'source_url' => $sourceUrl,
                'status' => 'ready',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Mark a video job as failed.
     */
    public function markFailed(int $assetId, string $reason): void
    {
        $asset = $this->queryFactory->create()->table('media_assets')
            ->where('id', '=', $assetId)
            ->first();

        if ($asset === null) {
            return;
        }

        $metadata = json_decode($asset['metadata'] ?? '{}', true) ?: [];
        $metadata['error'] = $reason;

        $this->queryFactory->create()->table('media_assets')
            ->where('id', '=', $assetId)
            ->update([
                'status' => 'failed',
                'metadata' => json_encode($metadata),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Get all media assets for a campaign.
     *
     * @return array<int, array<string, mixed>>
     */
    public function assetsForCampaign(int $campaignId): array
    {
        return $this->queryFactory->create()->table('media_assets')
            ->where('campaign_id', '=', $campaignId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
}
