<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Tests\Queue;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SoftLab\MessengerMonitorBundle\Queue\DoctrineQueueStatsProvider;
use SoftLab\MessengerMonitorBundle\Queue\QueueInfo;

final class DoctrineQueueStatsProviderTest extends TestCase
{
    private Connection&MockObject $connection;
    private DoctrineQueueStatsProvider $provider;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')
            ->with('default')
            ->willReturn($this->connection);

        $this->provider = new DoctrineQueueStatsProvider($registry);
    }

    public function testGetQueuesReturnsQueueInfoArray(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['queue_name' => 'async', 'message_count' => '5', 'oldest_at' => '2025-01-15 10:00:00'],
            ['queue_name' => 'orders', 'message_count' => '12', 'oldest_at' => '2025-01-14 08:00:00'],
        ]);

        $this->connection->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $queues = $this->provider->getQueues();

        self::assertCount(2, $queues);

        self::assertSame('async', $queues[0]->name);
        self::assertSame(5, $queues[0]->messageCount);
        self::assertInstanceOf(\DateTimeImmutable::class, $queues[0]->oldestMessageAt);

        self::assertSame('orders', $queues[1]->name);
        self::assertSame(12, $queues[1]->messageCount);
    }

    public function testGetQueuesReturnsEmptyArrayWhenNoRows(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $this->connection->method('executeQuery')->willReturn($result);

        self::assertSame([], $this->provider->getQueues());
    }

    public function testGetQueuesHandlesNullOldestAt(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['queue_name' => 'async', 'message_count' => '0', 'oldest_at' => null],
        ]);

        $this->connection->method('executeQuery')->willReturn($result);

        $queues = $this->provider->getQueues();

        self::assertCount(1, $queues);
        self::assertNull($queues[0]->oldestMessageAt);
    }

    public function testGetQueueReturnsSingleQueue(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')->willReturn(
            ['message_count' => '7', 'oldest_at' => '2025-01-15 10:00:00'],
        );

        $this->connection->method('executeQuery')->willReturn($result);

        $queue = $this->provider->getQueue('async');

        self::assertNotNull($queue);
        self::assertSame('async', $queue->name);
        self::assertSame(7, $queue->messageCount);
    }

    public function testGetQueueReturnsNullWhenNotFound(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')->willReturn(false);

        $this->connection->method('executeQuery')->willReturn($result);

        self::assertNull($this->provider->getQueue('nonexistent'));
    }

    public function testGetTotalMessageCount(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn('42');

        $this->connection->method('executeQuery')->willReturn($result);

        self::assertSame(42, $this->provider->getTotalMessageCount());
    }

    public function testGetTotalMessageCountReturnsZero(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn('0');

        $this->connection->method('executeQuery')->willReturn($result);

        self::assertSame(0, $this->provider->getTotalMessageCount());
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

        $provider = new DoctrineQueueStatsProvider($registry, 'default', 'custom_messages');

        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn('3');

        $this->connection->expects(self::once())
            ->method('executeQuery')
            ->with(self::stringContains('custom_messages'))
            ->willReturn($result);

        self::assertSame(3, $provider->getTotalMessageCount());
    }
}
