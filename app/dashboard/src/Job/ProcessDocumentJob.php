<?php

declare(strict_types=1);

namespace App\Dashboard\Job;

use App\Dashboard\AppLocator;
use App\Dashboard\Service\KnowledgeBaseService;
use Marko\Log\Contracts\LoggerInterface;
use Marko\Queue\Job;

class ProcessDocumentJob extends Job
{
    public function __construct(
        private readonly int $documentId,
    ) {}

    public function handle(): void
    {
        $logger = AppLocator::get(LoggerInterface::class);
        $logger->info('Processing document job', ['document_id' => $this->documentId]);

        $service = AppLocator::get(KnowledgeBaseService::class);
        $result = $service->processDocumentEmbedding($this->documentId);

        if ($result === null) {
            $logger->error('Document processing failed', ['document_id' => $this->documentId]);
            throw new \RuntimeException('Document embedding failed.');
        }

        $logger->info('Document processed', [
            'document_id' => $this->documentId,
            'chunks' => $result['chunks'],
        ]);
    }
}
