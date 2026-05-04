<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\User\Context\UserContext;
use App\Dashboard\Gate\KnowledgeBaseGate;
use App\Dashboard\Repository\KnowledgeBaseRepository;
use App\Dashboard\Job\ProcessDocumentJob;
use App\Dashboard\Service\EmbeddingService;
use App\Dashboard\Service\KnowledgeBaseService;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Inertia\Inertia;
use Marko\Inertia\Middleware\InertiaMiddleware;
use Marko\Queue\QueueInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Validation\Contracts\ValidatorInterface;

#[Middleware([SessionMiddleware::class, AuthMiddleware::class, InertiaMiddleware::class])]
class KnowledgeBaseController
{
    public function __construct(
        private readonly Inertia $inertia,
        private readonly UserContext $userContext,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly KnowledgeBaseService $kbService,
        private readonly KnowledgeBaseGate $kbGate,
        private readonly SessionInterface $session,
        private readonly KnowledgeBaseRepository $kbRepo,
        private readonly EmbeddingService $embedder,
        private readonly QueueInterface $queue,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Get('/knowledge-base')]
    public function index(Request $request): Response
    {
        $userId = $this->userContext->id();

        $documents = $this->kbGate->documentsForUser($userId);
        $workspace = $this->kbGate->firstWorkspaceForUser($userId);

        return $this->inertia->render($request, 'KnowledgeBase/Index', [
            'documents' => $documents,
            'workspace' => $workspace,
        ]);
    }

    #[Get('/knowledge-base/{id}')]
    public function show(Request $request, int $id): Response
    {
        $document = $this->kbGate->documentForUser($this->userContext->id(), $id);

        if ($document === null) {
            return Response::redirect('/knowledge-base');
        }

        $document['metadata'] = json_decode($document['metadata'] ?? '{}', true);

        return $this->inertia->render($request, 'KnowledgeBase/Show', [
            'document' => $document,
        ]);
    }

    #[Post('/knowledge-base/upload')]
    public function upload(Request $request): Response
    {
        $userId = $this->userContext->id();
        $workspace = $this->kbGate->firstWorkspaceForUser($userId);

        if ($workspace === null) {
            $this->session->flash()->add('error', 'No workspace found.');
            return Response::redirect('/knowledge-base');
        }

        $file = $_FILES['document'] ?? null;

        if ($file === null || $file['tmp_name'] === '' || $file['error'] !== UPLOAD_ERR_OK) {
            $this->session->flash()->add('error', 'No file uploaded or upload failed.');
            return Response::redirect('/knowledge-base');
        }

        $allowedTypes = [
            'text/plain',
            'text/plain; charset=utf-8',
            'text/markdown',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
        ];

        $mimeType = $file['type'] ?: mime_content_type($file['tmp_name']);

        if (!in_array($mimeType, $allowedTypes, true)) {
            $this->session->flash()->add('error', 'Unsupported file type. Please upload TXT, MD, PDF, or DOCX files.');
            return Response::redirect('/knowledge-base');
        }

        $documentId = $this->kbService->createDocument(
            $file['tmp_name'],
            $file['name'],
            $mimeType,
            (int) $workspace['id'],
        );

        if ($documentId === null) {
            $this->session->flash()->add('error', 'Could not parse the uploaded file.');
            return Response::redirect('/knowledge-base');
        }

        $this->queue->push(new ProcessDocumentJob($documentId));

        $this->session->flash()->add('success', 'Document uploaded and queued for processing.');

        return Response::redirect('/knowledge-base');
    }

    #[Get('/api/knowledge-base/search')]
    public function search(Request $request): Response
    {
        $data = ['q' => $request->query('q') ?? ''];
        $errors = $this->validator->validate($data, [
            'q' => 'required|string|min:1',
        ]);

        if ($errors->isNotEmpty()) {
            return Response::json(['errors' => $errors->all(), 'results' => []], 422);
        }

        $query = $data['q'];

        $userId = $this->userContext->id();
        $workspace = $this->kbGate->firstWorkspaceForUser($userId);

        if ($workspace === null) {
            return Response::json(['error' => 'No workspace found.', 'results' => []], 404);
        }

        $embeddings = $this->embedder->embed([$query]);

        if ($embeddings === null || empty($embeddings[0])) {
            return Response::json([
                'error' => 'Embeddings unavailable. Check your OpenRouter API key.',
                'results' => [],
            ]);
        }

        $results = $this->kbRepo->findSimilarChunks($embeddings[0], (int) $workspace['id'], 5);

        return Response::json(['results' => $results]);
    }

    #[Post('/knowledge-base/{id}/delete')]
    public function delete(int $id): Response
    {
        $document = $this->kbGate->documentForUser($this->userContext->id(), $id);

        if ($document === null) {
            return Response::redirect('/knowledge-base');
        }

        $this->queryFactory->create()->table('knowledge_chunks')
            ->where('document_id', '=', $id)
            ->delete();

        $this->queryFactory->create()->table('knowledge_documents')
            ->where('id', '=', $id)
            ->delete();

        $this->session->flash()->add('success', 'Document deleted.');

        return Response::redirect('/knowledge-base');
    }
}
