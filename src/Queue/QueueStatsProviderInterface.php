<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Queue;

interface QueueStatsProviderInterface
{
    /**
     * @return QueueInfo[]
     */
    public function getQueues(): array;

    public function getQueue(string $name): ?QueueInfo;

    /**
     * Total pending messages across all queues.
     */
    public function getTotalMessageCount(): int;

    public function isAvailable(): bool;
}
