<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Twig;

use SoftLab\MessengerMonitorBundle\Failed\FailedMessageManager;
use SoftLab\MessengerMonitorBundle\History\MessageHistoryProviderInterface;
use SoftLab\MessengerMonitorBundle\Queue\QueueStatsProviderInterface;
use SoftLab\MessengerMonitorBundle\Supervisor\SupervisorManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MonitorExtension extends AbstractExtension
{
    public function __construct(
        private readonly SupervisorManagerInterface $supervisor,
        private readonly QueueStatsProviderInterface $queueStats,
        private readonly FailedMessageManager $failedMessages,
        private readonly MessageHistoryProviderInterface $history,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('messenger_workers', $this->supervisor->getWorkers(...)),
            new TwigFunction('messenger_queues', $this->queueStats->getQueues(...)),
            new TwigFunction('messenger_failed_count', $this->failedMessages->count(...)),
            new TwigFunction('messenger_total_pending', $this->queueStats->getTotalMessageCount(...)),
            new TwigFunction('messenger_history', $this->history->getHistory(...)),
            new TwigFunction('messenger_history_stats', $this->history->getStats(...)),
            new TwigFunction('messenger_history_stats_by_queue', $this->history->getStatsByQueue(...)),
        ];
    }
}
