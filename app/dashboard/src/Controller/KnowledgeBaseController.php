<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Authorization\WorkspaceAuthorization;
use App\Dashboard\Service\KnowledgeBaseService;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Inertia\Inertia;
use Marko\Inertia\Middleware\InertiaMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Middleware\SessionMiddleware;

#[Middleware([SessionMiddleware::class, AuthMiddleware::class, InertiaMiddleware::class])]
class KnowledgeBaseController
{
    public function __construct(
        private readonly Inertia $inertia,
        private readonly AuthManager $auth,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly KnowledgeBaseService $kbService,
        private readonly WorkspaceAuthorization $workspaceAuth,
        private readonly SessionInterface $session,
        private readonly \App\Dashboard\Service\VectorSearchService $vectorSearch,
        private readonly \App\Dashboard\Service\EmbeddingService $embedder,
    ) {}

    #[Get('/knowledge-base')]
    public function index(Request $request): Response
    {
        $userId = $this->auth->id() ?? 0;
        $workspace = $this->workspaceAuth->firstWorkspaceFor($userId);

        $documents = [];

        if ($workspace !== null) {
            $documents = $this->queryFactory->create()->table('knowledge_documents')
                ->where('workspace_id', '=', $workspace['id'])
                ->orderBy('created_at', 'DESC')
                ->get();
        }

        return $this->inertia->render($request, 'KnowledgeBase/Index', [
            'documents' => $documents,
            'workspace' => $workspace,
        ]);
    }

    #[Get('/knowledge-base/{id}')]
    public function show(Request $request, int $id): Response
    {
        $document = $this->workspaceAuth->documentFor($this->auth->id() ?? 0, $id);

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
        $userId = $this->auth->id() ?? 0;
        $workspace = $this->workspaceAuth->firstWorkspaceFor($userId);

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
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
        ];

        $mimeType = $file['type'] ?: mime_content_type($file['tmp_name']);

        if (!in_array($mimeType, $allowedTypes, true)) {
            $this->session->flash()->add('error', 'Unsupported file type. Please upload TXT, PDF, or DOCX files.');
            return Response::redirect('/knowledge-base');
        }

        $result = $this->kbService->processUpload(
            $file['tmp_name'],
            $file['name'],
            $mimeType,
            (int) $workspace['id'],
        );

        if ($result === null) {
            $this->session->flash()->add('error', 'Could not parse the uploaded file.');
            return Response::redirect('/knowledge-base');
        }

        $this->session->flash()->add('success', "Document uploaded and split into {$result['chunks']} chunks.");

        return Response::redirect('/knowledge-base');
    }

    #[Get('/api/knowledge-base/search')]
    public function search(Request $request): Response
    {
        $query = $request->query('q') ?? '';

        if (trim($query) === '') {
            return Response::json(['error' => 'Query is required.', 'results' => []], 422);
        }

        $userId = $this->auth->id() ?? 0;
        $workspace = $this->workspaceAuth->firstWorkspaceFor($userId);

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

        $results = $this->vectorSearch->search($embeddings[0], 5, (int) $workspace['id']);

        return Response::json(['results' => $results]);
    }

    #[Post('/knowledge-base/{id}/delete')]
    public function delete(int $id): Response
    {
        $document = $this->workspaceAuth->documentFor($this->auth->id() ?? 0, $id);

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
