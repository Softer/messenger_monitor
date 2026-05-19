<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Tests\History;

use PHPUnit\Framework\TestCase;
use SoftLab\MessengerMonitorBundle\History\MessageHistoryEntry;

final class MessageHistoryEntryTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $date = new \DateTimeImmutable('2025-01-15 10:30:00');
        $entry = new MessageHistoryEntry(
            id: 1,
            messageClass: 'App\\Message\\SendNotification',
            queueName: 'async',
            status: 'handled',
            errorMessage: null,
            durationMs: 1500,
            memoryBytes: 65536,
            dispatchedAt: $date,
        );

        self::assertSame(1, $entry->id);
        self::assertSame('App\\Message\\SendNotification', $entry->messageClass);
        self::assertSame('async', $entry->queueName);
        self::assertSame('handled', $entry->status);
        self::assertNull($entry->errorMessage);
        self::assertSame(1500, $entry->durationMs);
        self::assertSame(65536, $entry->memoryBytes);
        self::assertSame($date, $entry->dispatchedAt);
    }

    public function testFailedEntry(): void
    {
        $entry = new MessageHistoryEntry(
            id: 2,
            messageClass: 'App\\Message\\Foo',
            queueName: 'async',
            status: 'failed',
            errorMessage: 'Connection refused',
            durationMs: 50,
            memoryBytes: 1024,
            dispatchedAt: new \DateTimeImmutable(),
        );

        self::assertSame('failed', $entry->status);
        self::assertSame('Connection refused', $entry->errorMessage);
    }

    public function testNullableDuration(): void
    {
        $entry = new MessageHistoryEntry(
            id: 3,
            messageClass: 'App\\Message\\Foo',
            queueName: 'async',
            status: 'handled',
            errorMessage: null,
            durationMs: null,
            memoryBytes: null,
            dispatchedAt: new \DateTimeImmutable(),
        );

        self::assertNull($entry->durationMs);
    }

    public function testGetShortClass(): void
    {
        $entry = new MessageHistoryEntry(
            id: 1,
            messageClass: 'App\\Message\\Orders\\SendNotification',
            queueName: 'async',
            status: 'handled',
            errorMessage: null,
            durationMs: null,
            memoryBytes: null,
            dispatchedAt: new \DateTimeImmutable(),
        );

        self::assertSame('SendNotification', $entry->getShortClass());
    }

    public function testGetShortClassWithoutNamespace(): void
    {
        $entry = new MessageHistoryEntry(
            id: 1,
            messageClass: 'SimpleMessage',
            queueName: 'async',
            status: 'handled',
            errorMessage: null,
            durationMs: null,
            memoryBytes: null,
            dispatchedAt: new \DateTimeImmutable(),
        );

        self::assertSame('SimpleMessage', $entry->getShortClass());
    }
}
