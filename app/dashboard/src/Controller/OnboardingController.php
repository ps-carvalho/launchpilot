<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Authorization\WorkspaceAuthorization;
use App\Dashboard\Http\RequestBodyParser;
use App\Dashboard\Service\KnowledgeBaseService;
use App\Dashboard\Service\WebsiteScraper;
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
class OnboardingController
{
    public function __construct(
        private readonly Inertia $inertia,
        private readonly AuthManager $auth,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly WebsiteScraper $scraper,
        private readonly KnowledgeBaseService $kbService,
        private readonly WorkspaceAuthorization $workspaceAuth,
        private readonly SessionInterface $session,
        private readonly RequestBodyParser $bodyParser,
    ) {}

    #[Get('/onboarding')]
    public function show(Request $request): Response
    {
        if ($this->hasCompletedOnboarding()) {
            return Response::redirect('/dashboard');
        }

        return $this->inertia->render($request, 'Onboarding/Index', [
            'errors' => $this->session->flash()->all(),
        ]);
    }

    #[Post('/onboarding')]
    public function submit(Request $request): Response
    {
        if ($this->hasCompletedOnboarding()) {
            return Response::redirect('/dashboard');
        }

        $url = $this->bodyParser->get($request, 'url');

        if (empty($url)) {
            $this->session->flash()->add('error', 'Please enter a website URL.');
            return Response::redirect('/onboarding');
        }

        $data = $this->scraper->scrape($url);

        if ($data === null) {
            $this->session->flash()->add('error', 'Could not scrape that URL. Please check the address and try again.');
            return Response::redirect('/onboarding');
        }

        $workspace = $this->workspaceAuth->firstWorkspaceFor($this->auth->id() ?? 0);

        if ($workspace === null) {
            $this->session->flash()->add('error', 'No workspace found.');
            return Response::redirect('/onboarding');
        }

        $rawText = implode("\n\n", array_filter([
            $data['title'] ? "Title: {$data['title']}" : null,
            $data['description'] ? "Description: {$data['description']}" : null,
            $data['body'] ? "Content:\n{$data['body']}" : null,
        ]));

        $documentId = $this->queryFactory->create()->table('knowledge_documents')->insert([
            'workspace_id' => $workspace['id'],
            'source_url' => $url,
            'original_name' => parse_url($url, PHP_URL_HOST) ?: $url,
            'raw_text' => $rawText,
            'metadata' => json_encode([
                'title' => $data['title'],
                'description' => $data['description'],
                'source' => 'website_scrape',
            ]),
        ]);

        $this->kbService->processScrapedDocument($documentId);

        $this->session->flash()->add('success', 'Website added to your knowledge base!');

        return Response::redirect('/dashboard');
    }

    private function hasCompletedOnboarding(): bool
    {
        $userId = $this->auth->id() ?? 0;
        $workspace = $this->workspaceAuth->firstWorkspaceFor($userId);

        if ($workspace === null) {
            return false;
        }

        $doc = $this->queryFactory->create()->table('knowledge_documents')
            ->where('workspace_id', '=', $workspace['id'])
            ->first();

        return $doc !== null;
    }
}
