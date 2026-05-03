<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Authorization\WorkspaceAuthorization;
use App\Dashboard\Context\UserContext;
use App\Dashboard\Flow\OnboardingFlow;
use App\Dashboard\Http\RequestBodyParser;
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
        private readonly UserContext $userContext,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly OnboardingFlow $onboardingFlow,
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

        $result = $this->onboardingFlow->complete($this->userContext->id(), $url);

        if (!$result['success']) {
            $this->session->flash()->add('error', $result['error']);
            return Response::redirect('/onboarding');
        }

        $this->session->flash()->add('success', 'Website added to your knowledge base!');

        return Response::redirect('/dashboard');
    }

    private function hasCompletedOnboarding(): bool
    {
        $userId = $this->userContext->id();
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
