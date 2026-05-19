<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Queue;

final readonly class QueueInfo
{
    public function __construct(
        public string $name,
        public int $messageCount,
        public ?\DateTimeImmutable $oldestMessageAt = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->messageCount === 0;
    }
}
