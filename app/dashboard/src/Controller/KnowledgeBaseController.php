<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Inertia\Inertia;
use Marko\Inertia\Middleware\InertiaMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Middleware\SessionMiddleware;

#[Middleware([SessionMiddleware::class, AuthMiddleware::class, InertiaMiddleware::class])]
class KnowledgeBaseController
{
    public function __construct(
        private readonly Inertia $inertia,
        private readonly AuthManager $auth,
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    #[Get('/knowledge-base')]
    public function index(Request $request): Response
    {
        $userId = $this->auth->id() ?? 0;

        $workspace = $this->queryFactory->create()->table('workspace_user')
            ->select('workspaces.id', 'workspaces.name')
            ->join('workspaces', 'workspace_user.workspace_id', '=', 'workspaces.id')
            ->where('workspace_user.user_id', '=', $userId)
            ->first();

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
        $userId = $this->auth->id() ?? 0;

        $workspace = $this->queryFactory->create()->table('workspace_user')
            ->select('workspaces.id')
            ->join('workspaces', 'workspace_user.workspace_id', '=', 'workspaces.id')
            ->where('workspace_user.user_id', '=', $userId)
            ->first();

        if ($workspace === null) {
            return Response::redirect('/knowledge-base');
        }

        $document = $this->queryFactory->create()->table('knowledge_documents')
            ->where('id', '=', $id)
            ->where('workspace_id', '=', $workspace['id'])
            ->first();

        if ($document === null) {
            return Response::redirect('/knowledge-base');
        }

        $document['metadata'] = json_decode($document['metadata'] ?? '{}', true);

        return $this->inertia->render($request, 'KnowledgeBase/Show', [
            'document' => $document,
        ]);
    }
}
