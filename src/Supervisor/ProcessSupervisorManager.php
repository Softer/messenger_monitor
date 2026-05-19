<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Supervisor;

use Symfony\Component\Process\Process;

class ProcessSupervisorManager implements SupervisorManagerInterface
{
    public function __construct(
        private readonly string $supervisorctlPath = 'supervisorctl',
        private readonly ?string $processGroup = null,
    ) {
    }

    public function getWorkers(): array
    {
        $output = $this->exec('status');

        if ($output === null) {
            return [];
        }

        $workers = [];

        foreach (explode("\n", trim($output)) as $line) {
            $worker = $this->parseLine($line);

            if ($worker === null) {
                continue;
            }

            if ($this->processGroup !== null && $worker->group !== $this->processGroup) {
                continue;
            }

            $workers[] = $worker;
        }

        return $workers;
    }

    public function getWorker(string $name): ?WorkerInfo
    {
        $output = $this->exec('status', $name);

        if ($output === null) {
            return null;
        }

        foreach (explode("\n", trim($output)) as $line) {
            $worker = $this->parseLine($line);

            if ($worker !== null) {
                return $worker;
            }
        }

        return null;
    }

    public function startWorker(string $name): bool
    {
        return $this->exec('start', $name) !== null;
    }

    public function stopWorker(string $name): bool
    {
        return $this->exec('stop', $name) !== null;
    }

    public function restartWorker(string $name): bool
    {
        return $this->exec('restart', $name) !== null;
    }

    public function isAvailable(): bool
    {
        return $this->exec('version') !== null;
    }

    protected function exec(string ...$args): ?string
    {
        $process = new Process([$this->supervisorctlPath, ...$args]);
        $process->setTimeout(10);

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        if (!$process->isSuccessful() && $args[0] !== 'status') {
            return null;
        }

        return $process->getOutput();
    }

    /**
     * Parses a single line of `supervisorctl status` output.
     *
     * Format: "name:process    STATUS    pid PID, uptime H:MM:SS"
     * or:     "name:process    STATUS    description"
     */
    private function parseLine(string $line): ?WorkerInfo
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        // Split by whitespace: name, status, rest...
        $parts = preg_split('/\s+/', $line, 3);

        if ($parts === false || \count($parts) < 2) {
            return null;
        }

        $fullName = $parts[0];
        $status = $parts[1];
        $description = $parts[2] ?? null;

        // Parse group:process_name
        if (str_contains($fullName, ':')) {
            [$group, $name] = explode(':', $fullName, 2);
        } else {
            $group = $fullName;
            $name = $fullName;
        }

        // Parse PID and uptime from description
        $pid = null;
        $uptime = null;

        if ($description !== null && preg_match('/pid\s+(\d+),\s*uptime\s+(\d+):(\d+):(\d+)/', $description, $m)) {
            $pid = (int) $m[1];
            $uptime = ((int) $m[2]) * 3600 + ((int) $m[3]) * 60 + (int) $m[4];
        }

        return new WorkerInfo(
            name: $name,
            group: $group,
            status: $status,
            pid: $pid,
            uptime: $uptime,
            description: $description,
        );
    }
}
