<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Authorization\WorkspaceAuthorization;
use App\Dashboard\Context\UserContext;
use App\Dashboard\Service\UsageQuota;
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
class DashboardController
{
    public function __construct(
        private readonly Inertia $inertia,
        private readonly UserContext $userContext,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly UsageQuota $usageQuota,
        private readonly WorkspaceAuthorization $workspaceAuth,
    ) {}

    #[Get('/dashboard')]
    public function index(Request $request): Response
    {
        $userId = $this->userContext->id();
        $userRow = $this->queryFactory->create()->table('users')->where('id', '=', $userId)->first();

        $workspaces = $this->workspaceAuth->workspacesFor($userId);
        $workspaceIds = array_column($workspaces, 'id');

        $campaigns = [];
        $documents = [];

        if (!empty($workspaceIds)) {
            $campaigns = $this->queryFactory->create()->table('campaigns')
                ->whereIn('workspace_id', $workspaceIds)
                ->whereNull('archived_at')
                ->orderBy('created_at', 'DESC')
                ->get();

            $documents = $this->queryFactory->create()->table('knowledge_documents')
                ->whereIn('workspace_id', $workspaceIds)
                ->orderBy('created_at', 'DESC')
                ->get();
        }

        $hasCompletedOnboarding = !empty($documents);

        return $this->inertia->render($request, 'Dashboard/Index', [
            'user' => [
                'id' => $userId,
                'name' => $userRow['name'] ?? 'User',
            ],
            'workspaces' => $workspaces,
            'campaigns' => $campaigns,
            'documents' => $documents,
            'hasCompletedOnboarding' => $hasCompletedOnboarding,
            'usage' => [
                'remaining' => $this->usageQuota->remaining($userId),
                'tier' => $this->usageQuota->tier($userId),
            ],
        ]);
    }
}
