<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Failed;

final readonly class FailedMessage
{
    public function __construct(
        public int|string $id,
        public string $class,
        public string $errorMessage,
        public \DateTimeImmutable $failedAt,
        public ?string $queueName = null,
    ) {
    }

    public function getShortClass(): string
    {
        $parts = explode('\\', $this->class);

        return end($parts);
    }
}
