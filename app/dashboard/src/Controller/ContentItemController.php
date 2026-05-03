<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Helper\JsonInput;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Inertia\Middleware\InertiaMiddleware;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Middleware\SessionMiddleware;

#[Middleware([SessionMiddleware::class, AuthMiddleware::class, InertiaMiddleware::class])]
class ContentItemController
{
    private const VALID_STATUSES = ['draft', 'approved', 'scheduled', 'published'];
    private const VALID_TRANSITIONS = [
        'draft' => ['approved', 'draft'],
        'approved' => ['scheduled', 'published', 'draft', 'approved'],
        'scheduled' => ['published', 'draft', 'approved', 'scheduled'],
        'published' => ['published'],
    ];

    public function __construct(
        private readonly AuthManager $auth,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly SessionInterface $session,
    ) {}

    #[Post('/api/content-items/{id}/status')]
    public function updateStatus(Request $request, int $id): Response
    {
        $newStatus = JsonInput::get($request, 'status');

        if (!in_array($newStatus, self::VALID_STATUSES, true)) {
            return Response::json(['error' => 'Invalid status.'], 422);
        }

        $item = $this->findItem($id);
        if ($item === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        $currentStatus = $item['status'];
        if (!in_array($newStatus, self::VALID_TRANSITIONS[$currentStatus] ?? [], true)) {
            return Response::json(['error' => "Cannot transition from {$currentStatus} to {$newStatus}."], 422);
        }

        $update = ['status' => $newStatus];

        if ($newStatus === 'published') {
            $update['published_at'] = date('Y-m-d H:i:s');
        }

        $this->queryFactory->create()->table('content_items')
            ->where('id', '=', $id)
            ->update($update);

        return Response::json(['success' => true, 'status' => $newStatus]);
    }

    #[Post('/api/content-items/{id}/edit')]
    public function edit(Request $request, int $id): Response
    {
        $content = JsonInput::get($request, 'content');

        if (empty($content)) {
            return Response::json(['error' => 'Content is required.'], 422);
        }

        $item = $this->findItem($id);
        if ($item === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        $this->queryFactory->create()->table('content_items')
            ->where('id', '=', $id)
            ->update([
                'content' => $content,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return Response::json(['success' => true]);
    }

    private function findItem(int $id): ?array
    {
        $userId = $this->auth->id() ?? 0;

        $workspaceIds = $this->queryFactory->create()->table('workspace_user')
            ->select('workspace_id')
            ->where('user_id', '=', $userId)
            ->get();

        $ids = array_column($workspaceIds, 'workspace_id');
        if (empty($ids)) {
            return null;
        }

        $item = $this->queryFactory->create()->table('content_items')
            ->where('id', '=', $id)
            ->first();

        if ($item === null) {
            return null;
        }

        $campaign = $this->queryFactory->create()->table('campaigns')
            ->where('id', '=', $item['campaign_id'])
            ->whereIn('workspace_id', $ids)
            ->first();

        return $campaign !== null ? $item : null;
    }
}
