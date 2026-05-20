<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Tests\History;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SoftLab\MessengerMonitorBundle\History\MessageHistoryRecorder;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

final class MessageHistoryRecorderTest extends TestCase
{
    private Connection&MockObject $connection;
    private MessageHistoryRecorder $recorder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')
            ->with('default')
            ->willReturn($this->connection);

        $this->recorder = new MessageHistoryRecorder($registry);
    }

    public function testOnHandledInsertsRow(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->connection->expects(self::once())
            ->method('insert')
            ->with(
                'messenger_monitor_history',
                self::callback(function (array $data): bool {
                    return $data['message_class'] === \stdClass::class
                        && $data['queue_name'] === 'async'
                        && $data['status'] === 'handled'
                        && $data['error_message'] === null;
                }),
            );

        $this->recorder->onReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
        $this->recorder->onHandled(new WorkerMessageHandledEvent($envelope, 'async'));
    }

    public function testOnFailedInsertsRowWithError(): void
    {
        $envelope = new Envelope(new \stdClass());
        $exception = new \RuntimeException('Something broke');

        $event = new WorkerMessageFailedEvent($envelope, 'async', $exception);

        $this->connection->expects(self::once())
            ->method('insert')
            ->with(
                'messenger_monitor_history',
                self::callback(function (array $data): bool {
                    return $data['status'] === 'failed'
                        && str_starts_with($data['error_message'], "Something broke\n#");
                }),
            );

        $this->recorder->onReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
        $this->recorder->onFailed($event);
    }

    public function testOnFailedWithRetryInsertsRetriedStatus(): void
    {
        $envelope = new Envelope(new \stdClass());
        $exception = new \RuntimeException('Temporary error');

        $event = new WorkerMessageFailedEvent($envelope, 'async', $exception);
        $event->setForRetry();

        $this->connection->expects(self::once())
            ->method('insert')
            ->with(
                'messenger_monitor_history',
                self::callback(function (array $data): bool {
                    return $data['status'] === 'retried';
                }),
            );

        $this->recorder->onReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
        $this->recorder->onFailed($event);
    }

    public function testDurationAndMemoryAreCalculated(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->connection->expects(self::once())
            ->method('insert')
            ->with(
                'messenger_monitor_history',
                self::callback(function (array $data): bool {
                    return $data['duration_ms'] !== null
                        && $data['duration_ms'] >= 0
                        && $data['memory_bytes'] !== null;
                }),
            );

        $this->recorder->onReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
        $this->recorder->onHandled(new WorkerMessageHandledEvent($envelope, 'async'));
    }

    public function testMetricsAreNullWithoutReceiveEvent(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->connection->expects(self::once())
            ->method('insert')
            ->with(
                'messenger_monitor_history',
                self::callback(function (array $data): bool {
                    return $data['duration_ms'] === null && $data['memory_bytes'] === null;
                }),
            );

        $this->recorder->onHandled(new WorkerMessageHandledEvent($envelope, 'async'));
    }

    public function testQueueNameFromEvent(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->connection->expects(self::once())
            ->method('insert')
            ->with(
                'messenger_monitor_history',
                self::callback(function (array $data): bool {
                    return $data['queue_name'] === 'orders';
                }),
            );

        $this->recorder->onReceived(new WorkerMessageReceivedEvent($envelope, 'orders'));
        $this->recorder->onHandled(new WorkerMessageHandledEvent($envelope, 'orders'));
    }

    public function testGetSubscribedEvents(): void
    {
        $events = MessageHistoryRecorder::getSubscribedEvents();

        self::assertArrayHasKey(WorkerMessageReceivedEvent::class, $events);
        self::assertArrayHasKey(WorkerMessageHandledEvent::class, $events);
        self::assertArrayHasKey(WorkerMessageFailedEvent::class, $events);
    }
}
