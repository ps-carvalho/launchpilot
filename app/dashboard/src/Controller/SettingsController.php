<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Helper\JsonInput;
use App\Dashboard\Service\ExportService;
use App\Dashboard\Service\GoogleSearchConsoleService;
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
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Middleware\SessionMiddleware;

#[Middleware([SessionMiddleware::class, AuthMiddleware::class, InertiaMiddleware::class])]
class SettingsController
{
    public function __construct(
        private readonly Inertia $inertia,
        private readonly AuthManager $auth,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly UserSettingsService $userSettings,
        private readonly GoogleSearchConsoleService $gscService,
        private readonly ExportService $exportService,
        private readonly SessionInterface $session,
    ) {}

    #[Get('/settings')]
    public function index(Request $request): Response
    {
        $userId = $this->auth->id() ?? 0;
        $settings = $this->userSettings->getOrCreate($userId);

        return $this->inertia->render($request, 'Settings/Index', [
            'settings' => [
                'tier' => $settings['tier'],
                'daily_runs_used' => $settings['daily_runs_used'],
                'remaining_runs' => $this->userSettings->getRemainingRuns($userId),
                'has_gsc' => !empty($settings['gsc_refresh_token']),
                'gsc_connected_at' => $settings['gsc_connected_at'],
                'has_custom_api_key' => !empty($settings['openrouter_api_key']),
            ],
            'gsc_configured' => $this->gscService->isConfigured(),
        ]);
    }

    #[Get('/settings/gsc/connect')]
    public function connectGsc(): Response
    {
        if (!$this->gscService->isConfigured()) {
            $this->session->flash()->add('error', 'Google Search Console is not configured by the administrator.');
            return Response::redirect('/settings');
        }

        $state = bin2hex(random_bytes(16));
        $this->session->set('gsc_oauth_state', $state);

        $redirectUri = 'http://localhost:8000/settings/gsc/callback';
        $url = $this->gscService->getAuthUrl($redirectUri, $state);

        return Response::redirect($url);
    }

    #[Get('/settings/gsc/callback')]
    public function gscCallback(Request $request): Response
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $savedState = $this->session->get('gsc_oauth_state');

        if (empty($code) || $state !== $savedState) {
            $this->session->flash()->add('error', 'Invalid OAuth callback.');
            return Response::redirect('/settings');
        }

        $redirectUri = 'http://localhost:8000/settings/gsc/callback';
        $tokens = $this->gscService->exchangeCode($code, $redirectUri);

        if ($tokens === null || empty($tokens['refresh_token'])) {
            $this->session->flash()->add('error', 'Failed to connect Google Search Console.');
            return Response::redirect('/settings');
        }

        $userId = $this->auth->id() ?? 0;
        $this->userSettings->getOrCreate($userId);

        $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->update([
                'gsc_refresh_token' => $tokens['refresh_token'],
                'gsc_connected_at' => date('Y-m-d H:i:s'),
            ]);

        $this->session->flash()->add('success', 'Google Search Console connected!');
        return Response::redirect('/settings');
    }

    #[Post('/settings/gsc/disconnect')]
    public function disconnectGsc(): Response
    {
        $userId = $this->auth->id() ?? 0;
        $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->update([
                'gsc_refresh_token' => null,
                'gsc_connected_at' => null,
            ]);

        $this->session->flash()->add('success', 'Google Search Console disconnected.');
        return Response::redirect('/settings');
    }

    #[Post('/settings/api-key')]
    public function updateApiKey(Request $request): Response
    {
        $userId = $this->auth->id() ?? 0;
        $settings = $this->userSettings->getOrCreate($userId);

        if ($settings['tier'] !== 'pro') {
            return Response::json(['error' => 'Premium feature. Upgrade to Pro.'], 403);
        }

        $key = JsonInput::get($request, 'api_key');
        $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->update([
                'openrouter_api_key' => $key ?: null,
            ]);

        return Response::json(['success' => true]);
    }

    #[Post('/settings/custom-prompts')]
    public function updateCustomPrompts(Request $request): Response
    {
        $userId = $this->auth->id() ?? 0;
        $settings = $this->userSettings->getOrCreate($userId);

        if ($settings['tier'] !== 'pro') {
            return Response::json(['error' => 'Premium feature. Upgrade to Pro.'], 403);
        }

        $prompts = JsonInput::get($request, 'prompts', []);
        $this->userSettings->updateCustomPrompts($userId, $prompts);

        return Response::json(['success' => true]);
    }

    #[Get('/api/gsc/data')]
    public function gscData(): Response
    {
        $userId = $this->auth->id() ?? 0;
        $settings = $this->userSettings->getOrCreate($userId);

        if (empty($settings['gsc_refresh_token'])) {
            return Response::json(['error' => 'GSC not connected.'], 400);
        }

        $tokens = $this->gscService->refreshToken($settings['gsc_refresh_token']);
        if ($tokens === null || empty($tokens['access_token'])) {
            return Response::json(['error' => 'Failed to refresh GSC token.'], 500);
        }

        $sites = $this->gscService->listSites($tokens['access_token']);
        if (empty($sites)) {
            return Response::json(['data' => []]);
        }

        $siteUrl = $sites[0]['siteUrl'] ?? null;
        if ($siteUrl === null) {
            return Response::json(['data' => []]);
        }

        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-90 days'));

        $data = $this->gscService->getSearchAnalytics(
            $tokens['access_token'],
            $siteUrl,
            $startDate,
            $endDate
        );

        return Response::json([
            'site_url' => $siteUrl,
            'data' => $data ?? [],
        ]);
    }

    #[Get('/settings/export')]
    public function export(): Response
    {
        $userId = $this->auth->id() ?? 0;

        $workspace = $this->queryFactory->create()->table('workspace_user')
            ->select('workspaces.id')
            ->join('workspaces', 'workspace_user.workspace_id', '=', 'workspaces.id')
            ->where('workspace_user.user_id', '=', $userId)
            ->first();

        if ($workspace === null) {
            return Response::redirect('/settings');
        }

        $markdown = $this->exportService->exportKnowledgeBase((int) $workspace['id']);

        return Response::make($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="launchpilot-knowledge-base.md"',
        ]);
    }
}
