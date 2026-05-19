<?php

declare(strict_types=1);

namespace App\Message;

final readonly class FailingMessage
{
    public function __construct(
        public string $text,
    ) {
    }
}
