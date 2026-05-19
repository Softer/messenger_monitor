<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TestMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TestMessageHandler
{
    public function __invoke(TestMessage $message): void
    {
        usleep(random_int(500_000, 2_000_000));
    }
}
