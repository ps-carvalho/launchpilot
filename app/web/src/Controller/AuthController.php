<?php

declare(strict_types=1);

namespace App\Web\Controller;

use Marko\Authentication\AuthManager;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\View\ViewInterface;

#[Middleware([SessionMiddleware::class, CsrfMiddleware::class])]
class AuthController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly QueryBuilderFactoryInterface $queryFactory,
        private readonly AuthManager $auth,
        private readonly PasswordHasherInterface $hasher,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly SessionInterface $session,
    ) {}

    #[Get('/login')]
    public function showLogin(): Response
    {
        return $this->view->render('auth/login', [
            'csrf_token' => $this->csrf->get(),
            'flash' => $this->flattenFlash($this->session->flash()->all()),
        ]);
    }

    #[Post('/login')]
    public function login(Request $request): Response
    {
        $credentials = [
            'email' => $request->post('email'),
            'password' => $request->post('password'),
        ];

        if ($this->auth->attempt($credentials)) {
            return Response::redirect('/dashboard');
        }

        $this->session->flash()->add('error', 'Invalid credentials.');

        return Response::redirect('/login');
    }

    #[Get('/register')]
    public function showRegister(): Response
    {
        return $this->view->render('auth/register', [
            'csrf_token' => $this->csrf->get(),
            'flash' => $this->flattenFlash($this->session->flash()->all()),
        ]);
    }

    #[Post('/register')]
    public function register(Request $request): Response
    {
        $name = $request->post('name');
        $email = $request->post('email');
        $password = $request->post('password');

        $existing = $this->queryFactory->create()->table('users')->where('email', '=', $email)->first();

        if ($existing !== null) {
            $this->session->flash()->add('error', 'Email already registered.');

            return Response::redirect('/register');
        }

        $userId = $this->queryFactory->create()->table('users')->insert([
            'name' => $name,
            'email' => $email,
            'password' => $this->hasher->hash($password),
        ]);

        $slug = $this->slugify($name . ' workspace');
        $workspaceId = $this->queryFactory->create()->table('workspaces')->insert([
            'name' => $name . "'s Workspace",
            'slug' => $slug,
            'owner_id' => $userId,
        ]);

        $this->queryFactory->create()->table('workspace_user')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'role' => 'owner',
        ]);

        $this->auth->guard()->loginById($userId);

        return Response::redirect('/dashboard');
    }

    private function flattenFlash(array $flash): array
    {
        $messages = [];
        foreach ($flash as $type => $typeMessages) {
            foreach ($typeMessages as $message) {
                $messages[] = $message;
            }
        }
        return $messages;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        $base = $text ?: 'workspace';

        $suffix = substr(uniqid(), -6);

        return $base . '-' . $suffix;
    }
}
