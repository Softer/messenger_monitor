<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\History;

final readonly class MessageHistoryEntry
{
    public function __construct(
        public int $id,
        public string $messageClass,
        public string $queueName,
        public string $status,
        public ?string $errorMessage,
        public ?int $durationMs,
        public ?int $memoryBytes,
        public \DateTimeImmutable $dispatchedAt,
    ) {
    }

    public function getShortClass(): string
    {
        $parts = explode('\\', $this->messageClass);

        return end($parts);
    }
}
