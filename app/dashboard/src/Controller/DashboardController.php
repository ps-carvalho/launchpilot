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
class DashboardController
{
    public function __construct(
        private readonly Inertia $inertia,
        private readonly AuthManager $auth,
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    #[Get('/dashboard')]
    public function index(Request $request): Response
    {
        $userId = $this->auth->id() ?? 0;
        $userRow = $this->queryFactory->create()->table('users')->where('id', '=', $userId)->first();

        $workspaces = $this->queryFactory->create()->table('workspace_user')
            ->select('workspaces.id', 'workspaces.name', 'workspaces.slug')
            ->join('workspaces', 'workspace_user.workspace_id', '=', 'workspaces.id')
            ->where('workspace_user.user_id', '=', $userId)
            ->get();

        $workspaceIds = array_column($workspaces, 'id');

        $campaigns = [];

        if (!empty($workspaceIds)) {
            $campaigns = $this->queryFactory->create()->table('campaigns')
                ->whereIn('workspace_id', $workspaceIds)
                ->orderBy('created_at', 'DESC')
                ->get();
        }

        return $this->inertia->render($request, 'Dashboard/Index', [
            'user' => [
                'id' => $userId,
                'name' => $userRow['name'] ?? 'User',
            ],
            'workspaces' => $workspaces,
            'campaigns' => $campaigns,
        ]);
    }
}
