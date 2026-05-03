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
class CampaignController
{
    public function __construct(
        private readonly Inertia $inertia,
        private readonly AuthManager $auth,
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    #[Get('/campaigns/{id}')]
    public function show(Request $request, int $id): Response
    {
        $userId = $this->auth->id() ?? 0;

        $workspaceIds = $this->queryFactory->create()->table('workspace_user')
            ->select('workspace_id')
            ->where('user_id', '=', $userId)
            ->get();

        $ids = array_column($workspaceIds, 'workspace_id');

        if (empty($ids)) {
            return Response::redirect('/dashboard');
        }

        $campaign = $this->queryFactory->create()->table('campaigns')
            ->where('id', '=', $id)
            ->whereIn('workspace_id', $ids)
            ->first();

        if ($campaign === null) {
            return Response::redirect('/dashboard');
        }

        $contentItems = $this->queryFactory->create()->table('content_items')
            ->where('campaign_id', '=', $id)
            ->orderBy('created_at', 'DESC')
            ->get();

        $sessions = $this->queryFactory->create()->table('agent_sessions')
            ->where('campaign_id', '=', $id)
            ->where('user_id', '=', $userId)
            ->orderBy('updated_at', 'DESC')
            ->get();

        return $this->inertia->render($request, 'Campaign/Show', [
            'campaign' => $campaign,
            'contentItems' => $contentItems,
            'sessions' => $sessions,
        ]);
    }
}
