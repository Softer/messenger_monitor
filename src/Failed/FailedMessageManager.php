<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Failed;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class FailedMessageManager
{
    public function __construct(
        private readonly ?TransportInterface $failedTransport,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @return FailedMessage[]
     */
    public function list(int $limit = 50): array
    {
        if (!$this->failedTransport instanceof ListableReceiverInterface) {
            return [];
        }

        $messages = [];

        foreach ($this->failedTransport->all($limit) as $envelope) {
            $idStamp = $envelope->last(TransportMessageIdStamp::class);

            if ($idStamp === null) {
                continue;
            }

            $id = $idStamp->getId();

            $errorMessage = '';
            $failedAt = new \DateTimeImmutable();

            $errorStamps = $envelope->all(ErrorDetailsStamp::class);

            if (\count($errorStamps) > 0) {
                $lastError = end($errorStamps);
                $errorMessage = $lastError->getExceptionMessage();
            }

            $failureStamp = $envelope->last(SentToFailureTransportStamp::class);
            $queueName = $failureStamp?->getOriginalReceiverName();

            $messages[] = new FailedMessage(
                id: $id,
                class: $envelope->getMessage()::class,
                errorMessage: $errorMessage,
                failedAt: $failedAt,
                queueName: $queueName,
            );
        }

        return $messages;
    }

    public function retry(int|string $id): bool
    {
        if (!$this->failedTransport instanceof ListableReceiverInterface) {
            return false;
        }

        $envelope = $this->failedTransport->find($id);

        if ($envelope === null) {
            return false;
        }

        $this->bus->dispatch($envelope->getMessage());
        $this->failedTransport->reject($envelope);

        return true;
    }

    public function remove(int|string $id): bool
    {
        if (!$this->failedTransport instanceof ListableReceiverInterface) {
            return false;
        }

        $envelope = $this->failedTransport->find($id);

        if ($envelope === null) {
            return false;
        }

        $this->failedTransport->reject($envelope);

        return true;
    }

    public function count(): int
    {
        if ($this->failedTransport instanceof MessageCountAwareInterface) {
            return $this->failedTransport->getMessageCount();
        }

        return 0;
    }

    public function isAvailable(): bool
    {
        return $this->failedTransport !== null;
    }
}
