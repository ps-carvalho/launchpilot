<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\User\Context\UserContext;
use App\Dashboard\Gate\CampaignGate;
use App\Dashboard\Gate\ContentItemGate;
use App\Dashboard\Http\RequestBodyParser;
use App\Dashboard\Service\AgentModelResolver;
use App\Dashboard\Service\ExportService;
use App\Dashboard\Service\UsageQuota;
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
use Marko\Validation\Contracts\ValidatorInterface;

#[Middleware([SessionMiddleware::class, AuthMiddleware::class, InertiaMiddleware::class])]
class CampaignController
{
    private const VALID_TYPES = ['one_off', 'recurring', 'ongoing'];
    private const VALID_STATUSES = ['draft', 'active', 'completed'];

    public function __construct(
        private readonly Inertia $inertia,
        private readonly UserContext $userContext,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly CampaignGate $campaignGate,
        private readonly ContentItemGate $contentItemGate,
        private readonly RequestBodyParser $bodyParser,
        private readonly UsageQuota $usageQuota,
        private readonly ExportService $exportService,
        private readonly AgentModelResolver $agentModelResolver,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Get('/campaigns')]
    public function index(Request $request): Response
    {
        $userId = $this->userContext->id();
        $tab = $request->query('tab') ?? 'active';
        $filter = in_array($tab, ['active', 'archived'], true) ? $tab : 'active';

        $archived = $filter === 'archived' ? true : false;
        $campaigns = $this->campaignGate->campaignsForUser($userId, null, $archived);

        return $this->inertia->render($request, 'Campaign/Index', [
            'campaigns' => $campaigns,
            'filter' => $filter,
        ]);
    }

    #[Get('/campaigns/create')]
    public function create(Request $request): Response
    {
        $userId = $this->userContext->id();
        $workspaces = $this->campaignGate->workspacesForUser($userId);

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
        $userId = $this->userContext->id();

        $campaign = $this->campaignGate->forUser($userId, $id);

        if ($campaign === null) {
            return Response::redirect('/campaigns');
        }

        $contentItems = $this->contentItemGate->itemsForCampaign($id);

        $sessions = $this->queryFactory->create()->table('agent_sessions')
            ->where('campaign_id', '=', $id)
            ->where('user_id', '=', $userId)
            ->orderBy('updated_at', 'DESC')
            ->get();

        $remainingRuns = $this->usageQuota->remaining($userId);
        $isPro = $this->usageQuota->tier($userId) === 'pro';
        $agentModels = $isPro ? $this->agentModelResolver->getUserModels($userId) : [];

        $mediaAssets = $this->queryFactory->create()->table('media_assets')
            ->where('campaign_id', '=', $id)
            ->orderBy('created_at', 'DESC')
            ->get();

        return $this->inertia->render($request, 'Campaign/Show', [
            'campaign' => $campaign,
            'contentItems' => $contentItems,
            'sessions' => $sessions,
            'remainingRuns' => $remainingRuns,
            'isPro' => $isPro,
            'agentModels' => $agentModels,
            'mediaAssets' => $mediaAssets,
            'modalityModels' => $this->agentModelResolver->availableModels(),
        ]);
    }

    #[Get('/campaigns/{id}/export')]
    public function export(int $id): Response
    {
        $userId = $this->userContext->id();

        $campaign = $this->campaignGate->forUser($userId, $id);
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
        $userId = $this->userContext->id();
        $data = $this->bodyParser->all($request);
        $errors = $this->validator->validate($data, [
            'workspace_id' => 'required|integer',
            'title' => 'required|string|min:1|max:255',
            'description' => 'nullable|string|max:2000',
            'type' => 'nullable|in:one_off,recurring,ongoing',
            'goal' => 'nullable|string|max:1000',
            'channels' => 'nullable|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        if ($errors->isNotEmpty()) {
            return Response::json(['errors' => $errors->all()], 422);
        }

        $workspaceId = (int) ($data['workspace_id'] ?? 0);
        $workspaceIds = $this->campaignGate->workspaceIdsForUser($userId);
        if (!in_array($workspaceId, $workspaceIds, true)) {
            return Response::json(['error' => 'Invalid workspace.'], 403);
        }

        $title = trim((string) $data['title']);
        $description = trim((string) ($data['description'] ?? ''));
        $type = (string) ($data['type'] ?? 'one_off');
        $goal = trim((string) ($data['goal'] ?? ''));
        $channels = $data['channels'] ?? [];
        $startDate = (string) ($data['start_date'] ?? '');
        $endDate = (string) ($data['end_date'] ?? '');

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
        $userId = $this->userContext->id();

        $campaign = $this->campaignGate->forUser($userId, $id);
        if ($campaign === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        $data = $this->bodyParser->all($request);
        $errors = $this->validator->validate($data, [
            'title' => 'nullable|string|min:1|max:255',
            'description' => 'nullable|string|max:2000',
            'type' => 'nullable|in:one_off,recurring,ongoing',
            'status' => 'nullable|in:draft,active,completed',
            'goal' => 'nullable|string|max:1000',
            'channels' => 'nullable|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        if ($errors->isNotEmpty()) {
            return Response::json(['errors' => $errors->all()], 422);
        }

        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $type = $data['type'] ?? null;
        $status = $data['status'] ?? null;
        $goal = $data['goal'] ?? null;
        $channels = $data['channels'] ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

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
        $userId = $this->userContext->id();

        $campaign = $this->campaignGate->forUser($userId, $id);
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
        $userId = $this->userContext->id();

        $campaign = $this->campaignGate->forUser($userId, $id);
        if ($campaign === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        $this->queryFactory->create()->table('campaigns')
            ->where('id', '=', $id)
            ->update(['archived_at' => null]);

        return Response::json(['success' => true]);
    }
}
