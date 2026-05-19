<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Supervisor;

final readonly class WorkerInfo
{
    public function __construct(
        public string $name,
        public string $group,
        public string $status,
        public ?int $pid,
        public ?int $uptime,
        public ?string $description,
    ) {
    }

    public function isRunning(): bool
    {
        return $this->status === 'RUNNING';
    }

    public function isStopped(): bool
    {
        return \in_array($this->status, ['STOPPED', 'EXITED', 'FATAL'], true);
    }
}
