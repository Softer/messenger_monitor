<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\History;

interface MessageHistoryProviderInterface
{
    /**
     * @return MessageHistoryEntry[]
     */
    public function getHistory(int $limit = 50): array;

    /**
     * @return array{handled: int, failed: int, retried: int}
     */
    public function getStats(): array;

    /**
     * @return array<string, array{handled: int, failed: int, retried: int}>
     */
    public function getStatsByQueue(): array;

    public function isAvailable(): bool;
}
