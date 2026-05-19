<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\History;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineMessageHistoryProvider implements MessageHistoryProviderInterface
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly string $connectionName = 'default',
        private readonly string $tableName = 'messenger_monitor_history',
    ) {
    }

    public function getHistory(int $limit = 50): array
    {
        try {
            return $this->fetchHistory($limit);
        } catch (TableNotFoundException) {
            return [];
        }
    }

    /** @return MessageHistoryEntry[] */
    private function fetchHistory(int $limit): array
    {
        $rows = $this->connection()->executeQuery(
            \sprintf(
                'SELECT id, message_class, queue_name, status, error_message, duration_ms, memory_bytes, dispatched_at FROM %s ORDER BY id DESC LIMIT %d',
                $this->tableName,
                $limit,
            ),
        )->fetchAllAssociative();

        return array_map(
            static fn(array $row) => new MessageHistoryEntry(
                id: (int) $row['id'],
                messageClass: $row['message_class'],
                queueName: $row['queue_name'],
                status: $row['status'],
                errorMessage: $row['error_message'],
                durationMs: $row['duration_ms'] !== null ? (int) $row['duration_ms'] : null,
                memoryBytes: $row['memory_bytes'] !== null ? (int) $row['memory_bytes'] : null,
                dispatchedAt: new \DateTimeImmutable($row['dispatched_at']),
            ),
            $rows,
        );
    }

    public function getStats(): array
    {
        try {
            return $this->fetchStats();
        } catch (TableNotFoundException) {
            return ['handled' => 0, 'failed' => 0, 'retried' => 0];
        }
    }

    /** @return array{handled: int, failed: int, retried: int} */
    private function fetchStats(): array
    {
        $rows = $this->connection()->executeQuery(
            \sprintf(
                'SELECT status, COUNT(*) AS cnt FROM %s GROUP BY status',
                $this->tableName,
            ),
        )->fetchAllAssociative();

        $stats = ['handled' => 0, 'failed' => 0, 'retried' => 0];

        foreach ($rows as $row) {
            $status = $row['status'];
            if (isset($stats[$status])) {
                $stats[$status] = (int) $row['cnt'];
            }
        }

        return $stats;
    }

    public function getStatsByQueue(): array
    {
        try {
            return $this->fetchStatsByQueue();
        } catch (TableNotFoundException) {
            return [];
        }
    }

    /** @return array<string, array{handled: int, failed: int, retried: int}> */
    private function fetchStatsByQueue(): array
    {
        $rows = $this->connection()->executeQuery(
            \sprintf(
                'SELECT queue_name, status, COUNT(*) AS cnt FROM %s GROUP BY queue_name, status',
                $this->tableName,
            ),
        )->fetchAllAssociative();

        $result = [];

        foreach ($rows as $row) {
            $queue = $row['queue_name'];
            $status = $row['status'];

            if (!isset($result[$queue])) {
                $result[$queue] = ['handled' => 0, 'failed' => 0, 'retried' => 0];
            }

            if (isset($result[$queue][$status])) {
                $result[$queue][$status] = (int) $row['cnt'];
            }
        }

        return $result;
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
