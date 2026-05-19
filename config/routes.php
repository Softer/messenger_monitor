<?php

declare(strict_types=1);

use SoftLab\MessengerMonitorBundle\Controller\MonitorApiController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import(MonitorApiController::class, 'attribute')
        ->prefix('/api/messenger-monitor')
    ;
};
