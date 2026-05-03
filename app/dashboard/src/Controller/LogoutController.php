<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;
use Marko\Session\Middleware\SessionMiddleware;

#[Middleware([SessionMiddleware::class, AuthMiddleware::class])]
class LogoutController
{
    public function __construct(
        private readonly AuthManager $auth,
    ) {}

    #[Get('/logout')]
    public function logout(): Response
    {
        $this->auth->logout();

        return Response::redirect('/');
    }
}
