<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Tests\History;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SoftLab\MessengerMonitorBundle\History\DoctrineMessageHistoryProvider;

final class DoctrineMessageHistoryProviderTest extends TestCase
{
    private Connection&MockObject $connection;
    private DoctrineMessageHistoryProvider $provider;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')
            ->with('default')
            ->willReturn($this->connection);

        $this->provider = new DoctrineMessageHistoryProvider($registry);
    }

    public function testGetHistoryReturnsEntries(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            [
                'id' => '1',
                'message_class' => 'App\\Message\\TestMessage',
                'queue_name' => 'async',
                'status' => 'handled',
                'error_message' => null,
                'duration_ms' => '1500',
                'memory_bytes' => '65536',
                'dispatched_at' => '2025-01-15 10:00:00',
            ],
            [
                'id' => '2',
                'message_class' => 'App\\Message\\FailingMessage',
                'queue_name' => 'async',
                'status' => 'failed',
                'error_message' => 'Error!',
                'duration_ms' => '50',
                'memory_bytes' => '1024',
                'dispatched_at' => '2025-01-15 10:01:00',
            ],
        ]);

        $this->connection->method('executeQuery')->willReturn($result);

        $entries = $this->provider->getHistory();

        self::assertCount(2, $entries);

        self::assertSame(1, $entries[0]->id);
        self::assertSame('App\\Message\\TestMessage', $entries[0]->messageClass);
        self::assertSame('async', $entries[0]->queueName);
        self::assertSame('handled', $entries[0]->status);
        self::assertNull($entries[0]->errorMessage);
        self::assertSame(1500, $entries[0]->durationMs);

        self::assertSame(2, $entries[1]->id);
        self::assertSame('failed', $entries[1]->status);
        self::assertSame('Error!', $entries[1]->errorMessage);
    }

    public function testGetHistoryReturnsEmptyArray(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $this->connection->method('executeQuery')->willReturn($result);

        self::assertSame([], $this->provider->getHistory());
    }

    public function testGetHistoryHandlesNullDuration(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            [
                'id' => '1',
                'message_class' => 'App\\Message\\Foo',
                'queue_name' => 'async',
                'status' => 'handled',
                'error_message' => null,
                'duration_ms' => null,
                'memory_bytes' => null,
                'dispatched_at' => '2025-01-15 10:00:00',
            ],
        ]);

        $this->connection->method('executeQuery')->willReturn($result);

        $entries = $this->provider->getHistory();

        self::assertNull($entries[0]->durationMs);
    }

    public function testGetStatsReturnsCounts(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['status' => 'handled', 'cnt' => '100'],
            ['status' => 'failed', 'cnt' => '5'],
            ['status' => 'retried', 'cnt' => '12'],
        ]);

        $this->connection->method('executeQuery')->willReturn($result);

        $stats = $this->provider->getStats();

        self::assertSame(100, $stats['handled']);
        self::assertSame(5, $stats['failed']);
        self::assertSame(12, $stats['retried']);
    }

    public function testGetStatsReturnsZerosWhenEmpty(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $this->connection->method('executeQuery')->willReturn($result);

        $stats = $this->provider->getStats();

        self::assertSame(0, $stats['handled']);
        self::assertSame(0, $stats['failed']);
        self::assertSame(0, $stats['retried']);
    }

    public function testIsAvailableReturnsTrue(): void
    {
        $result = $this->createMock(Result::class);
        $this->connection->method('executeQuery')->willReturn($result);

        self::assertTrue($this->provider->isAvailable());
    }

    public function testIsAvailableReturnsFalseOnException(): void
    {
        $this->connection->method('executeQuery')
            ->willThrowException(new \RuntimeException('Table not found'));

        self::assertFalse($this->provider->isAvailable());
    }

    public function testCustomTableName(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn($this->connection);

        $provider = new DoctrineMessageHistoryProvider($registry, 'default', 'custom_history');

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $this->connection->expects(self::once())
            ->method('executeQuery')
            ->with(self::stringContains('custom_history'))
            ->willReturn($result);

        $provider->getHistory();
    }
}
