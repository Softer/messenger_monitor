<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Controller;

use SoftLab\MessengerMonitorBundle\Failed\FailedMessageManager;
use SoftLab\MessengerMonitorBundle\History\MessageHistoryProviderInterface;
use SoftLab\MessengerMonitorBundle\Queue\QueueStatsProviderInterface;
use SoftLab\MessengerMonitorBundle\Supervisor\SupervisorManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MonitorApiController
{
    public function __construct(
        private readonly SupervisorManagerInterface $supervisor,
        private readonly QueueStatsProviderInterface $queueStats,
        private readonly FailedMessageManager $failedMessages,
        private readonly MessageHistoryProviderInterface $history,
    ) {
    }

    #[Route('/workers', name: 'softlab_messenger_monitor_workers', methods: ['GET'])]
    public function workers(): JsonResponse
    {
        $workers = array_map(
            static fn($w) => [
                'name' => $w->name,
                'group' => $w->group,
                'status' => $w->status,
                'pid' => $w->pid,
                'uptime' => $w->uptime,
                'description' => $w->description,
                'running' => $w->isRunning(),
            ],
            $this->supervisor->getWorkers(),
        );

        return new JsonResponse(['workers' => $workers]);
    }

    #[Route('/workers/{name}/start', name: 'softlab_messenger_monitor_worker_start', methods: ['POST'])]
    public function startWorker(string $name): JsonResponse
    {
        $result = $this->supervisor->startWorker($name);

        return new JsonResponse(['success' => $result], $result ? 200 : 404);
    }

    #[Route('/workers/{name}/stop', name: 'softlab_messenger_monitor_worker_stop', methods: ['POST'])]
    public function stopWorker(string $name): JsonResponse
    {
        $result = $this->supervisor->stopWorker($name);

        return new JsonResponse(['success' => $result], $result ? 200 : 404);
    }

    #[Route('/workers/{name}/restart', name: 'softlab_messenger_monitor_worker_restart', methods: ['POST'])]
    public function restartWorker(string $name): JsonResponse
    {
        $result = $this->supervisor->restartWorker($name);

        return new JsonResponse(['success' => $result], $result ? 200 : 404);
    }

    #[Route('/queues', name: 'softlab_messenger_monitor_queues', methods: ['GET'])]
    public function queues(): JsonResponse
    {
        $queues = array_map(
            static fn($q) => [
                'name' => $q->name,
                'message_count' => $q->messageCount,
                'oldest_message_at' => $q->oldestMessageAt?->format('c'),
            ],
            $this->queueStats->getQueues(),
        );

        return new JsonResponse(['queues' => $queues]);
    }

    #[Route('/failed', name: 'softlab_messenger_monitor_failed', methods: ['GET'])]
    public function failed(): JsonResponse
    {
        $messages = array_map(
            static fn($m) => [
                'id' => $m->id,
                'class' => $m->class,
                'short_class' => $m->getShortClass(),
                'error' => $m->errorMessage,
                'failed_at' => $m->failedAt->format('c'),
                'queue_name' => $m->queueName,
            ],
            $this->failedMessages->list(),
        );

        return new JsonResponse([
            'messages' => $messages,
            'count' => $this->failedMessages->count(),
        ]);
    }

    #[Route('/failed/{id}/retry', name: 'softlab_messenger_monitor_failed_retry', methods: ['POST'])]
    public function retryFailed(int|string $id): JsonResponse
    {
        $result = $this->failedMessages->retry($id);

        return new JsonResponse(['success' => $result], $result ? 200 : 404);
    }

    #[Route('/failed/{id}', name: 'softlab_messenger_monitor_failed_remove', methods: ['DELETE'])]
    public function removeFailed(int|string $id): JsonResponse
    {
        $result = $this->failedMessages->remove($id);

        return new JsonResponse(['success' => $result], $result ? 200 : 404);
    }

    #[Route('/history', name: 'softlab_messenger_monitor_history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        $entries = array_map(
            static fn($e) => [
                'id' => $e->id,
                'class' => $e->messageClass,
                'short_class' => $e->getShortClass(),
                'queue_name' => $e->queueName,
                'status' => $e->status,
                'error' => $e->errorMessage,
                'duration_ms' => $e->durationMs,
                'memory_bytes' => $e->memoryBytes,
                'dispatched_at' => $e->dispatchedAt->format('c'),
            ],
            $this->history->getHistory(),
        );

        return new JsonResponse([
            'entries' => $entries,
            'stats' => $this->history->getStats(),
        ]);
    }

    #[Route('/summary', name: 'softlab_messenger_monitor_summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        $workers = $this->supervisor->getWorkers();
        $runningCount = \count(array_filter($workers, static fn($w) => $w->isRunning()));

        return new JsonResponse([
            'workers_total' => \count($workers),
            'workers_running' => $runningCount,
            'queues_pending' => $this->queueStats->getTotalMessageCount(),
            'failed_count' => $this->failedMessages->count(),
            'supervisor_available' => $this->supervisor->isAvailable(),
        ]);
    }
}
