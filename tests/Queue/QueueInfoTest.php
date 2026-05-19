<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Tests\Queue;

use PHPUnit\Framework\TestCase;
use SoftLab\MessengerMonitorBundle\Queue\QueueInfo;

final class QueueInfoTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $date = new \DateTimeImmutable('2025-01-15 10:30:00');
        $queue = new QueueInfo(name: 'async', messageCount: 42, oldestMessageAt: $date);

        self::assertSame('async', $queue->name);
        self::assertSame(42, $queue->messageCount);
        self::assertSame($date, $queue->oldestMessageAt);
    }

    public function testOldestMessageAtDefaultsToNull(): void
    {
        $queue = new QueueInfo(name: 'async', messageCount: 0);

        self::assertNull($queue->oldestMessageAt);
    }

    public function testIsEmptyReturnsTrueWhenZeroMessages(): void
    {
        $queue = new QueueInfo(name: 'async', messageCount: 0);

        self::assertTrue($queue->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenHasMessages(): void
    {
        $queue = new QueueInfo(name: 'async', messageCount: 1);

        self::assertFalse($queue->isEmpty());
    }
}
