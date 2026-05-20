<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\History;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

final class MessageHistoryRecorder implements EventSubscriberInterface
{
    private ?float $lastReceivedAt = null;
    private ?int $lastMemoryUsage = null;
    private bool $autoSetup = true;

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly string $connectionName = 'default',
        private readonly string $tableName = 'messenger_monitor_history',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onReceived',
            WorkerMessageHandledEvent::class => 'onHandled',
            WorkerMessageFailedEvent::class => 'onFailed',
        ];
    }

    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->lastReceivedAt = microtime(true);
        $this->lastMemoryUsage = memory_get_usage();
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->record($event, 'handled');
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $throwable = $event->getThrowable();
        $error = $throwable->getMessage() . "\n" . $throwable->getTraceAsString();
        $this->record($event, $event->willRetry() ? 'retried' : 'failed', $error);
    }

    private function record(AbstractWorkerMessageEvent $event, string $status, ?string $errorMessage = null): void
    {
        $durationMs = null;
        $memoryBytes = null;

        if ($this->lastReceivedAt !== null) {
            $durationMs = (int) ((microtime(true) - $this->lastReceivedAt) * 1000);
            $this->lastReceivedAt = null;
        }

        if ($this->lastMemoryUsage !== null) {
            $memoryBytes = memory_get_usage() - $this->lastMemoryUsage;
            $this->lastMemoryUsage = null;
        }

        $data = [
            'message_class' => $event->getEnvelope()->getMessage()::class,
            'queue_name' => $event->getReceiverName(),
            'status' => $status,
            'error_message' => $errorMessage,
            'duration_ms' => $durationMs,
            'memory_bytes' => $memoryBytes,
            'dispatched_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        try {
            $this->connection()->insert($this->tableName, $data);
        } catch (TableNotFoundException) {
            if ($this->autoSetup) {
                $this->setup();
                $this->connection()->insert($this->tableName, $data);
            }
        }
    }

    private function setup(): void
    {
        $connection = $this->connection();
        $schemaManager = $connection->createSchemaManager();

        if ($schemaManager->tablesExist([$this->tableName])) {
            $this->autoSetup = false;

            return;
        }

        $table = new Table($this->tableName);
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true]);
        $table->addColumn('message_class', Types::STRING, ['length' => 255]);
        $table->addColumn('queue_name', Types::STRING, ['length' => 190]);
        $table->addColumn('status', Types::STRING, ['length' => 16]);
        $table->addColumn('error_message', Types::TEXT, ['notnull' => false]);
        $table->addColumn('duration_ms', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('memory_bytes', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('dispatched_at', Types::DATETIME_IMMUTABLE);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['dispatched_at'], 'idx_dispatched_at');
        $table->addIndex(['status'], 'idx_status');
        $table->addIndex(['queue_name'], 'idx_queue_name');

        try {
            $schemaManager->createTable($table);
        } catch (\Throwable) {
            // another worker might have created the table concurrently
        }

        $this->autoSetup = false;
    }

    private function connection(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->registry->getConnection($this->connectionName);

        return $connection;
    }
}
