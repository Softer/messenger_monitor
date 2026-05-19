<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Tests\Failed;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SoftLab\MessengerMonitorBundle\Failed\FailedMessageManager;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

interface CountableListableTransport extends TransportInterface, ListableReceiverInterface, MessageCountAwareInterface
{
}

final class FailedMessageManagerTest extends TestCase
{
    private CountableListableTransport&MockObject $transport;
    private MessageBusInterface&MockObject $bus;
    private FailedMessageManager $manager;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(CountableListableTransport::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->manager = new FailedMessageManager($this->transport, $this->bus);
    }

    public function testListReturnsFailedMessages(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message, [new TransportMessageIdStamp(42)]);

        $this->transport->method('all')
            ->with(50)
            ->willReturn([$envelope]);

        $messages = $this->manager->list();

        self::assertCount(1, $messages);
        self::assertSame(42, $messages[0]->id);
        self::assertSame(\stdClass::class, $messages[0]->class);
    }

    public function testListReturnsEmptyWhenTransportNotListable(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $manager = new FailedMessageManager($transport, $this->bus);

        self::assertSame([], $manager->list());
    }

    public function testListReturnsEmptyWhenTransportIsNull(): void
    {
        $manager = new FailedMessageManager(null, $this->bus);

        self::assertSame([], $manager->list());
    }

    public function testListSkipsMessagesWithoutIdStamp(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->transport->method('all')->willReturn([$envelope]);

        $messages = $this->manager->list();

        self::assertCount(0, $messages);
    }

    public function testListRespectsLimit(): void
    {
        $this->transport->expects(self::once())
            ->method('all')
            ->with(10)
            ->willReturn([]);

        $this->manager->list(10);
    }

    public function testRetryDispatchesMessageAndRejects(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);

        $this->transport->method('find')
            ->with(42)
            ->willReturn($envelope);

        $this->bus->expects(self::once())
            ->method('dispatch')
            ->with($message)
            ->willReturn($envelope);

        $this->transport->expects(self::once())
            ->method('reject')
            ->with($envelope);

        self::assertTrue($this->manager->retry(42));
    }

    public function testRetryReturnsFalseWhenNotFound(): void
    {
        $this->transport->method('find')
            ->with(99)
            ->willReturn(null);

        $this->bus->expects(self::never())->method('dispatch');

        self::assertFalse($this->manager->retry(99));
    }

    public function testRetryReturnsFalseWhenTransportNotListable(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $manager = new FailedMessageManager($transport, $this->bus);

        self::assertFalse($manager->retry(1));
    }

    public function testRemoveRejectsEnvelope(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->transport->method('find')
            ->with(42)
            ->willReturn($envelope);

        $this->transport->expects(self::once())
            ->method('reject')
            ->with($envelope);

        self::assertTrue($this->manager->remove(42));
    }

    public function testRemoveReturnsFalseWhenNotFound(): void
    {
        $this->transport->method('find')
            ->with(99)
            ->willReturn(null);

        self::assertFalse($this->manager->remove(99));
    }

    public function testRemoveReturnsFalseWhenTransportNotListable(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $manager = new FailedMessageManager($transport, $this->bus);

        self::assertFalse($manager->remove(1));
    }

    public function testCountReturnsTransportCount(): void
    {
        $this->transport->method('getMessageCount')->willReturn(5);

        self::assertSame(5, $this->manager->count());
    }

    public function testCountReturnsZeroWhenTransportNotCountable(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $manager = new FailedMessageManager($transport, $this->bus);

        self::assertSame(0, $manager->count());
    }

    public function testCountReturnsZeroWhenTransportIsNull(): void
    {
        $manager = new FailedMessageManager(null, $this->bus);

        self::assertSame(0, $manager->count());
    }

    public function testIsAvailableReturnsTrueWithTransport(): void
    {
        self::assertTrue($this->manager->isAvailable());
    }

    public function testIsAvailableReturnsFalseWithoutTransport(): void
    {
        $manager = new FailedMessageManager(null, $this->bus);

        self::assertFalse($manager->isAvailable());
    }
}
