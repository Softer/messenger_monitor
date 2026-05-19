<?php

declare(strict_types=1);

namespace App\Controller;

use App\Message\FailingMessage;
use App\Message\TestMessage;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class DemoController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('/', name: 'demo_index', methods: ['GET'])]
    public function index(): Response
    {
        return new Response($this->twig->render('index.html.twig'));
    }

    #[Route('/produce', name: 'demo_produce', methods: ['POST'])]
    public function produce(Request $request): Response
    {
        $count = max(1, min(100, (int) $request->request->get('count', 1)));
        $failing = $request->request->getBoolean('failing');

        for ($i = 0; $i < $count; $i++) {
            if ($failing) {
                $this->bus->dispatch(new FailingMessage("message #$i"));
            } else {
                $this->bus->dispatch(new TestMessage("message #$i"));
            }
        }

        return new RedirectResponse('/');
    }
}
