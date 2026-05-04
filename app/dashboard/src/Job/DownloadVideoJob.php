<?php

declare(strict_types=1);

namespace App\Dashboard\Job;

use App\Dashboard\AppLocator;
use App\Dashboard\Service\VideoGenerationService;
use Marko\Log\Contracts\LoggerInterface;
use Marko\Queue\Job;

class DownloadVideoJob extends Job
{
    public function __construct(
        private readonly int $assetId,
        private readonly string $jobId,
        private readonly int $userId,
        private readonly string $downloadUrl,
        private readonly int $campaignId,
    ) {}

    public function handle(): void
    {
        $logger = AppLocator::get(LoggerInterface::class);
        $logger->info('Downloading video job', [
            'asset_id' => $this->assetId,
            'job_id' => $this->jobId,
        ]);

        $service = AppLocator::get(VideoGenerationService::class);

        $filename = $this->assetId . '_' . time() . '.mp4';
        $localPath = $service->download(
            $this->downloadUrl,
            (string) $this->campaignId,
            $filename,
            $this->userId,
        );

        if ($localPath === null) {
            $logger->error('Video download job failed', [
                'asset_id' => $this->assetId,
                'job_id' => $this->jobId,
            ]);
            $service->markFailed($this->assetId, 'Download failed in queue worker.');
            throw new \RuntimeException('Video download failed.');
        }

        $service->markReady($this->assetId, $localPath, $this->downloadUrl);

        $logger->info('Video download job completed', [
            'asset_id' => $this->assetId,
            'local_path' => $localPath,
        ]);
    }
}
