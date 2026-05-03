<?php

declare(strict_types=1);

namespace App\Web\Controller;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class HomeController
{
    public function __construct(
        private readonly ViewInterface $view,
    ) {}

    #[Get('/')]
    public function index(): Response
    {
        return $this->view->render('home/index', [
            'title' => 'LaunchPilot AI — Marketing plans built for owner-led businesses',
        ]);
    }
}
