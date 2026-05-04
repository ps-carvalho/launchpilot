<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\User\Context\UserContext;
use App\Dashboard\Gate\ContentItemGate;
use App\Dashboard\Http\RequestBodyParser;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Inertia\Middleware\InertiaMiddleware;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Validation\Contracts\ValidatorInterface;

#[Middleware([SessionMiddleware::class, AuthMiddleware::class, InertiaMiddleware::class])]
class ContentItemController
{
    private const VALID_STATUSES = ['draft', 'approved', 'scheduled', 'published'];
    private const VALID_TRANSITIONS = [
        'draft' => ['approved'],
        'approved' => ['scheduled', 'published', 'draft'],
        'scheduled' => ['published', 'draft', 'approved'],
        'published' => [],
    ];

    public function __construct(
        private readonly UserContext $userContext,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly ContentItemGate $itemGate,
        private readonly RequestBodyParser $bodyParser,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Post('/api/content-items/{id}/status')]
    public function updateStatus(Request $request, int $id): Response
    {
        $data = $this->bodyParser->all($request);
        $errors = $this->validator->validate($data, [
            'status' => 'required|in:draft,approved,scheduled,published',
        ]);

        if ($errors->isNotEmpty()) {
            return Response::json(['errors' => $errors->all()], 422);
        }

        $newStatus = $data['status'];

        $item = $this->itemGate->itemForUser($this->userContext->id(), $id);
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
        $data = $this->bodyParser->all($request);
        $errors = $this->validator->validate($data, [
            'content' => 'required|string|min:1',
        ]);

        if ($errors->isNotEmpty()) {
            return Response::json(['errors' => $errors->all()], 422);
        }

        $content = $data['content'];

        $item = $this->itemGate->itemForUser($this->userContext->id(), $id);
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

    #[Post('/api/content-items/{id}/delete')]
    public function delete(int $id): Response
    {
        $item = $this->itemGate->itemForUser($this->userContext->id(), $id);
        if ($item === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        $this->queryFactory->create()->table('content_items')
            ->where('id', '=', $id)
            ->update([
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);

        return Response::json(['success' => true]);
    }
}
