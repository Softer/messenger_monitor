<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SoftLab\MessengerMonitorBundle\Controller\MonitorApiController;
use SoftLab\MessengerMonitorBundle\Failed\FailedMessageManager;
use SoftLab\MessengerMonitorBundle\History\DoctrineMessageHistoryProvider;
use SoftLab\MessengerMonitorBundle\History\MessageHistoryProviderInterface;
use SoftLab\MessengerMonitorBundle\History\MessageHistoryRecorder;
use SoftLab\MessengerMonitorBundle\Queue\DoctrineQueueStatsProvider;
use SoftLab\MessengerMonitorBundle\Queue\QueueStatsProviderInterface;
use SoftLab\MessengerMonitorBundle\Supervisor\ProcessSupervisorManager;
use SoftLab\MessengerMonitorBundle\Twig\MonitorExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->set(ProcessSupervisorManager::class);

    $services->set(DoctrineQueueStatsProvider::class);
    $services->alias(QueueStatsProviderInterface::class, DoctrineQueueStatsProvider::class);

    $services->set(FailedMessageManager::class)
        ->arg('$failedTransport', service('messenger.transport.failed')->nullOnInvalid())
    ;

    $services->set(DoctrineMessageHistoryProvider::class);
    $services->alias(MessageHistoryProviderInterface::class, DoctrineMessageHistoryProvider::class);

    $services->set(MessageHistoryRecorder::class)
        ->tag('kernel.event_subscriber')
    ;

    $services->set(MonitorExtension::class)
        ->tag('twig.extension')
    ;

    $services->set(MonitorApiController::class)
        ->tag('controller.service_arguments')
    ;
};
