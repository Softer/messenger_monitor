<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Tests\Supervisor;

use PHPUnit\Framework\TestCase;
use SoftLab\MessengerMonitorBundle\Supervisor\ProcessSupervisorManager;

final class ProcessSupervisorManagerTest extends TestCase
{
    public function testGetWorkersParsesSupervisorctlOutput(): void
    {
        $output = implode("\n", [
            'messenger:async_worker_00       RUNNING   pid 1234, uptime 1:30:45',
            'messenger:async_worker_01       RUNNING   pid 1235, uptime 0:15:20',
            'messenger:orders_worker_00      STOPPED   Jan 15 10:30 AM',
        ]);

        $manager = $this->createManager($output);
        $workers = $manager->getWorkers();

        self::assertCount(3, $workers);

        self::assertSame('async_worker_00', $workers[0]->name);
        self::assertSame('messenger', $workers[0]->group);
        self::assertSame('RUNNING', $workers[0]->status);
        self::assertSame(1234, $workers[0]->pid);
        self::assertSame(5445, $workers[0]->uptime);

        self::assertSame('async_worker_01', $workers[1]->name);
        self::assertSame(1235, $workers[1]->pid);
        self::assertSame(920, $workers[1]->uptime);

        self::assertSame('orders_worker_00', $workers[2]->name);
        self::assertSame('STOPPED', $workers[2]->status);
        self::assertNull($workers[2]->pid);
        self::assertNull($workers[2]->uptime);
    }

    public function testGetWorkersFiltersEmptyLines(): void
    {
        $output = "messenger:worker_00       RUNNING   pid 100, uptime 0:00:10\n\n\n";

        $manager = $this->createManager($output);
        $workers = $manager->getWorkers();

        self::assertCount(1, $workers);
    }

    public function testGetWorkersReturnsEmptyArrayOnNullOutput(): void
    {
        $manager = $this->createManager(null);

        self::assertSame([], $manager->getWorkers());
    }

    public function testGetWorkersFiltersByProcessGroup(): void
    {
        $output = implode("\n", [
            'messenger:async_worker_00       RUNNING   pid 1234, uptime 0:10:00',
            'cron:job_runner_00              RUNNING   pid 5678, uptime 1:00:00',
        ]);

        $manager = $this->createManager($output, processGroup: 'messenger');
        $workers = $manager->getWorkers();

        self::assertCount(1, $workers);
        self::assertSame('async_worker_00', $workers[0]->name);
    }

    public function testGetWorkersHandlesProcessWithoutGroup(): void
    {
        $output = 'standalone_process       RUNNING   pid 999, uptime 0:05:00';

        $manager = $this->createManager($output);
        $workers = $manager->getWorkers();

        self::assertCount(1, $workers);
        self::assertSame('standalone_process', $workers[0]->name);
        self::assertSame('standalone_process', $workers[0]->group);
    }

    public function testGetWorkerReturnsSingleWorker(): void
    {
        $output = 'messenger:async_worker_00       RUNNING   pid 1234, uptime 0:30:00';

        $manager = $this->createManager($output);
        $worker = $manager->getWorker('messenger:async_worker_00');

        self::assertNotNull($worker);
        self::assertSame('async_worker_00', $worker->name);
        self::assertSame(1234, $worker->pid);
    }

    public function testGetWorkerReturnsNullOnNoOutput(): void
    {
        $manager = $this->createManager(null);

        self::assertNull($manager->getWorker('nonexistent'));
    }

    public function testGetWorkerReturnsNullOnUnparsableLine(): void
    {
        $manager = $this->createManager('');

        self::assertNull($manager->getWorker('test'));
    }

    public function testParseLineHandlesLargeUptime(): void
    {
        $output = 'messenger:worker_00       RUNNING   pid 1, uptime 100:59:59';

        $manager = $this->createManager($output);
        $workers = $manager->getWorkers();

        self::assertCount(1, $workers);
        self::assertSame(363599, $workers[0]->uptime);
    }

    public function testStartWorkerReturnsTrue(): void
    {
        $manager = $this->createManager('started');

        self::assertTrue($manager->startWorker('test'));
    }

    public function testStartWorkerReturnsFalseOnFailure(): void
    {
        $manager = $this->createManager(null);

        self::assertFalse($manager->startWorker('test'));
    }

    public function testStopWorkerReturnsTrue(): void
    {
        $manager = $this->createManager('stopped');

        self::assertTrue($manager->stopWorker('test'));
    }

    public function testRestartWorkerReturnsTrue(): void
    {
        $manager = $this->createManager('restarted');

        self::assertTrue($manager->restartWorker('test'));
    }

    public function testIsAvailableReturnsTrue(): void
    {
        $manager = $this->createManager('4.2.5');

        self::assertTrue($manager->isAvailable());
    }

    public function testIsAvailableReturnsFalseOnFailure(): void
    {
        $manager = $this->createManager(null);

        self::assertFalse($manager->isAvailable());
    }

    public function testDescriptionIsPreserved(): void
    {
        $output = 'messenger:worker_00       STOPPED   Not started';

        $manager = $this->createManager($output);
        $workers = $manager->getWorkers();

        self::assertSame('Not started', $workers[0]->description);
    }

    private function createManager(?string $execOutput, ?string $processGroup = null): ProcessSupervisorManager
    {
        return new class ($execOutput, $processGroup) extends ProcessSupervisorManager {
            public function __construct(
                private readonly ?string $execOutput,
                ?string $processGroup,
            ) {
                parent::__construct('supervisorctl', $processGroup);
            }

            protected function exec(string ...$args): ?string
            {
                return $this->execOutput;
            }
        };
    }
}
