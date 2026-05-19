<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Queue;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineQueueStatsProvider implements QueueStatsProviderInterface
{
    /** @param string[] $knownQueues */
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly string $connectionName = 'default',
        private readonly string $tableName = 'messenger_messages',
        private readonly array $knownQueues = [],
    ) {
    }

    public function getQueues(): array
    {
        $rows = $this->connection()->executeQuery(
            \sprintf(
                'SELECT queue_name, COUNT(*) AS message_count, MIN(created_at) AS oldest_at FROM %s WHERE delivered_at IS NULL GROUP BY queue_name ORDER BY queue_name',
                $this->tableName,
            ),
        )->fetchAllAssociative();

        $queues = [];
        $seen = [];

        foreach ($rows as $row) {
            $seen[] = $row['queue_name'];
            $queues[] = new QueueInfo(
                name: $row['queue_name'],
                messageCount: (int) $row['message_count'],
                oldestMessageAt: $row['oldest_at'] !== null ? new \DateTimeImmutable($row['oldest_at']) : null,
            );
        }

        foreach ($this->knownQueues as $name) {
            if (!\in_array($name, $seen, true)) {
                $queues[] = new QueueInfo(name: $name, messageCount: 0);
            }
        }

        return $queues;
    }

    public function getQueue(string $name): ?QueueInfo
    {
        $row = $this->connection()->executeQuery(
            \sprintf(
                'SELECT COUNT(*) AS message_count, MIN(created_at) AS oldest_at FROM %s WHERE queue_name = ? AND delivered_at IS NULL',
                $this->tableName,
            ),
            [$name],
        )->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return new QueueInfo(
            name: $name,
            messageCount: (int) $row['message_count'],
            oldestMessageAt: $row['oldest_at'] !== null ? new \DateTimeImmutable($row['oldest_at']) : null,
        );
    }

    public function getTotalMessageCount(): int
    {
        $result = $this->connection()->executeQuery(
            \sprintf(
                'SELECT COUNT(*) FROM %s WHERE delivered_at IS NULL',
                $this->tableName,
            ),
        )->fetchOne();

        return (int) $result;
    }

    public function isAvailable(): bool
    {
        try {
            $this->connection()->executeQuery(
                \sprintf('SELECT 1 FROM %s LIMIT 1', $this->tableName),
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function connection(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->registry->getConnection($this->connectionName);

        return $connection;
    }
}
