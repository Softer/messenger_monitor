<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Supervisor;

interface SupervisorManagerInterface
{
    /**
     * @return WorkerInfo[]
     */
    public function getWorkers(): array;

    public function getWorker(string $name): ?WorkerInfo;

    public function startWorker(string $name): bool;

    public function stopWorker(string $name): bool;

    public function restartWorker(string $name): bool;

    public function isAvailable(): bool;
}
