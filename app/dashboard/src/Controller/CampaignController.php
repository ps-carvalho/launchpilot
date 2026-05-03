<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Authorization\WorkspaceAuthorization;
use App\Dashboard\Http\RequestBodyParser;
use App\Dashboard\Service\ExportService;
use App\Dashboard\Service\UserSettingsService;
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
use Marko\Session\Middleware\SessionMiddleware;

#[Middleware([SessionMiddleware::class, AuthMiddleware::class, InertiaMiddleware::class])]
class CampaignController
{
    private const VALID_TYPES = ['one_off', 'recurring', 'ongoing'];
    private const VALID_STATUSES = ['draft', 'active', 'completed'];

    public function __construct(
        private readonly Inertia $inertia,
        private readonly AuthManager $auth,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly WorkspaceAuthorization $workspaceAuth,
        private readonly RequestBodyParser $bodyParser,
        private readonly UserSettingsService $userSettings,
        private readonly ExportService $exportService,
    ) {}

    #[Get('/campaigns')]
    public function index(Request $request): Response
    {
        $userId = $this->auth->id() ?? 0;
        $workspaceIds = $this->workspaceAuth->workspaceIdsFor($userId);

        $tab = $request->query('tab') ?? 'active';
        $filter = in_array($tab, ['active', 'archived'], true) ? $tab : 'active';

        $campaigns = [];

        if (!empty($workspaceIds)) {
            $query = $this->queryFactory->create()->table('campaigns')
                ->whereIn('workspace_id', $workspaceIds);

            if ($filter === 'archived') {
                $query->whereNotNull('archived_at');
            } else {
                $query->whereNull('archived_at');
            }

            $campaigns = $query->orderBy('created_at', 'DESC')->get();
        }

        return $this->inertia->render($request, 'Campaign/Index', [
            'campaigns' => $campaigns,
            'filter' => $filter,
        ]);
    }

    #[Get('/campaigns/create')]
    public function create(Request $request): Response
    {
        $userId = $this->auth->id() ?? 0;
        $workspaces = $this->workspaceAuth->workspacesFor($userId);

        if (empty($workspaces)) {
            return Response::redirect('/dashboard');
        }

        return $this->inertia->render($request, 'Campaign/Create', [
            'workspaces' => $workspaces,
        ]);
    }

    #[Get('/campaigns/{id}')]
    public function show(Request $request, int $id): Response
    {
        $userId = $this->auth->id() ?? 0;

        $campaign = $this->workspaceAuth->campaignFor($userId, $id);

        if ($campaign === null) {
            return Response::redirect('/campaigns');
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

        $remainingRuns = $this->userSettings->getRemainingRuns($userId);

        return $this->inertia->render($request, 'Campaign/Show', [
            'campaign' => $campaign,
            'contentItems' => $contentItems,
            'sessions' => $sessions,
            'remainingRuns' => $remainingRuns,
        ]);
    }

    #[Get('/campaigns/{id}/export')]
    public function export(int $id): Response
    {
        $userId = $this->auth->id() ?? 0;

        $campaign = $this->workspaceAuth->campaignFor($userId, $id);
        if ($campaign === null) {
            return Response::redirect('/campaigns');
        }

        $markdown = $this->exportService->exportCampaign($id);
        $filename = preg_replace('/[^a-z0-9]+/', '-', strtolower($campaign['title'])) . '-export.md';

        return new Response(
            body: $markdown,
            statusCode: 200,
            headers: [
                'Content-Type' => 'text/markdown; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ],
        );
    }

    #[Post('/campaigns')]
    public function store(Request $request): Response
    {
        $userId = $this->auth->id() ?? 0;
        $workspaceId = (int) $this->bodyParser->get($request, 'workspace_id');

        $workspaceIds = $this->workspaceAuth->workspaceIdsFor($userId);
        if (!in_array($workspaceId, $workspaceIds, true)) {
            return Response::json(['error' => 'Invalid workspace.'], 403);
        }

        $title = trim((string) $this->bodyParser->get($request, 'title'));
        $description = trim((string) $this->bodyParser->get($request, 'description', ''));
        $type = (string) $this->bodyParser->get($request, 'type', 'one_off');
        $goal = trim((string) $this->bodyParser->get($request, 'goal', ''));
        $channels = $this->bodyParser->get($request, 'channels', []);
        $startDate = (string) $this->bodyParser->get($request, 'start_date', '');
        $endDate = (string) $this->bodyParser->get($request, 'end_date', '');

        if (empty($title)) {
            return Response::json(['error' => 'Title is required.'], 422);
        }

        if (!in_array($type, self::VALID_TYPES, true)) {
            $type = 'one_off';
        }

        $insert = [
            'workspace_id' => $workspaceId,
            'title' => $title,
            'description' => $description ?: null,
            'type' => $type,
            'status' => 'draft',
            'goal' => $goal ?: null,
            'channels' => is_array($channels) ? json_encode($channels) : '[]',
        ];

        if (!empty($startDate)) {
            $insert['start_date'] = $startDate;
        }
        if (!empty($endDate)) {
            $insert['end_date'] = $endDate;
        }

        $campaignId = $this->queryFactory->create()->table('campaigns')->insert($insert);

        return Response::json(['success' => true, 'campaign_id' => $campaignId]);
    }

    #[Post('/campaigns/{id}')]
    public function update(Request $request, int $id): Response
    {
        $userId = $this->auth->id() ?? 0;

        $campaign = $this->workspaceAuth->campaignFor($userId, $id);
        if ($campaign === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        $title = $this->bodyParser->get($request, 'title');
        $description = $this->bodyParser->get($request, 'description');
        $type = $this->bodyParser->get($request, 'type');
        $status = $this->bodyParser->get($request, 'status');
        $goal = $this->bodyParser->get($request, 'goal');
        $channels = $this->bodyParser->get($request, 'channels');
        $startDate = $this->bodyParser->get($request, 'start_date');
        $endDate = $this->bodyParser->get($request, 'end_date');

        $update = [];

        if ($title !== null) {
            $update['title'] = trim((string) $title);
        }
        if ($description !== null) {
            $update['description'] = trim((string) $description) ?: null;
        }
        if ($type !== null && in_array($type, self::VALID_TYPES, true)) {
            $update['type'] = $type;
        }
        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $update['status'] = $status;
        }
        if ($goal !== null) {
            $update['goal'] = trim((string) $goal) ?: null;
        }
        if ($channels !== null) {
            $update['channels'] = is_array($channels) ? json_encode($channels) : '[]';
        }
        if ($startDate !== null) {
            $update['start_date'] = !empty($startDate) ? $startDate : null;
        }
        if ($endDate !== null) {
            $update['end_date'] = !empty($endDate) ? $endDate : null;
        }

        if (empty($update)) {
            return Response::json(['error' => 'No fields to update.'], 422);
        }

        $this->queryFactory->create()->table('campaigns')
            ->where('id', '=', $id)
            ->update($update);

        return Response::json(['success' => true]);
    }

    #[Post('/campaigns/{id}/archive')]
    public function archive(Request $request, int $id): Response
    {
        $userId = $this->auth->id() ?? 0;

        $campaign = $this->workspaceAuth->campaignFor($userId, $id);
        if ($campaign === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        $this->queryFactory->create()->table('campaigns')
            ->where('id', '=', $id)
            ->update(['archived_at' => date('Y-m-d H:i:s')]);

        return Response::json(['success' => true]);
    }

    #[Post('/campaigns/{id}/restore')]
    public function restore(Request $request, int $id): Response
    {
        $userId = $this->auth->id() ?? 0;

        $campaign = $this->workspaceAuth->campaignFor($userId, $id);
        if ($campaign === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        $this->queryFactory->create()->table('campaigns')
            ->where('id', '=', $id)
            ->update(['archived_at' => null]);

        return Response::json(['success' => true]);
    }
}
