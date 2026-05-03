<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Context\UserContext;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;
use Marko\Session\Middleware\SessionMiddleware;

#[Middleware([SessionMiddleware::class, AuthMiddleware::class])]
class LogoutController
{
    public function __construct(
        private readonly UserContext $userContext,
    ) {}

    #[Get('/logout')]
    public function logout(): Response
    {
        $this->userContext->logout();

        return Response::redirect('/');
    }
}
