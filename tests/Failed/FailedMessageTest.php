<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Tests\Failed;

use PHPUnit\Framework\TestCase;
use SoftLab\MessengerMonitorBundle\Failed\FailedMessage;

final class FailedMessageTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $date = new \DateTimeImmutable('2025-01-15 10:30:00');
        $msg = new FailedMessage(
            id: 42,
            class: 'App\\Message\\SendNotification',
            errorMessage: 'Connection refused',
            failedAt: $date,
            queueName: 'async',
        );

        self::assertSame(42, $msg->id);
        self::assertSame('App\\Message\\SendNotification', $msg->class);
        self::assertSame('Connection refused', $msg->errorMessage);
        self::assertSame($date, $msg->failedAt);
        self::assertSame('async', $msg->queueName);
    }

    public function testQueueNameDefaultsToNull(): void
    {
        $msg = new FailedMessage(
            id: 1,
            class: 'App\\Message\\Foo',
            errorMessage: '',
            failedAt: new \DateTimeImmutable(),
        );

        self::assertNull($msg->queueName);
    }

    public function testStringId(): void
    {
        $msg = new FailedMessage(
            id: 'abc-123',
            class: 'App\\Message\\Foo',
            errorMessage: '',
            failedAt: new \DateTimeImmutable(),
        );

        self::assertSame('abc-123', $msg->id);
    }

    public function testGetShortClass(): void
    {
        $msg = new FailedMessage(
            id: 1,
            class: 'App\\Message\\Orders\\SendNotification',
            errorMessage: '',
            failedAt: new \DateTimeImmutable(),
        );

        self::assertSame('SendNotification', $msg->getShortClass());
    }

    public function testGetShortClassWithoutNamespace(): void
    {
        $msg = new FailedMessage(
            id: 1,
            class: 'SimpleMessage',
            errorMessage: '',
            failedAt: new \DateTimeImmutable(),
        );

        self::assertSame('SimpleMessage', $msg->getShortClass());
    }
}
