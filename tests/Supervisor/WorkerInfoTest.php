<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Tests\Supervisor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SoftLab\MessengerMonitorBundle\Supervisor\WorkerInfo;

final class WorkerInfoTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $worker = new WorkerInfo(
            name: 'async_worker_00',
            group: 'messenger',
            status: 'RUNNING',
            pid: 12345,
            uptime: 3661,
            description: 'pid 12345, uptime 1:01:01',
        );

        self::assertSame('async_worker_00', $worker->name);
        self::assertSame('messenger', $worker->group);
        self::assertSame('RUNNING', $worker->status);
        self::assertSame(12345, $worker->pid);
        self::assertSame(3661, $worker->uptime);
        self::assertSame('pid 12345, uptime 1:01:01', $worker->description);
    }

    public function testNullableFields(): void
    {
        $worker = new WorkerInfo(
            name: 'worker',
            group: 'group',
            status: 'STOPPED',
            pid: null,
            uptime: null,
            description: null,
        );

        self::assertNull($worker->pid);
        self::assertNull($worker->uptime);
        self::assertNull($worker->description);
    }

    public function testIsRunningReturnsTrueForRunningStatus(): void
    {
        $worker = new WorkerInfo('w', 'g', 'RUNNING', null, null, null);

        self::assertTrue($worker->isRunning());
    }

    #[DataProvider('nonRunningStatuses')]
    public function testIsRunningReturnsFalseForOtherStatuses(string $status): void
    {
        $worker = new WorkerInfo('w', 'g', $status, null, null, null);

        self::assertFalse($worker->isRunning());
    }

    #[DataProvider('stoppedStatuses')]
    public function testIsStoppedReturnsTrueForStoppedStatuses(string $status): void
    {
        $worker = new WorkerInfo('w', 'g', $status, null, null, null);

        self::assertTrue($worker->isStopped());
    }

    #[DataProvider('nonStoppedStatuses')]
    public function testIsStoppedReturnsFalseForNonStoppedStatuses(string $status): void
    {
        $worker = new WorkerInfo('w', 'g', $status, null, null, null);

        self::assertFalse($worker->isStopped());
    }

    /** @return iterable<string, array{string}> */
    public static function nonRunningStatuses(): iterable
    {
        yield 'STOPPED' => ['STOPPED'];
        yield 'EXITED' => ['EXITED'];
        yield 'FATAL' => ['FATAL'];
        yield 'STARTING' => ['STARTING'];
    }

    /** @return iterable<string, array{string}> */
    public static function stoppedStatuses(): iterable
    {
        yield 'STOPPED' => ['STOPPED'];
        yield 'EXITED' => ['EXITED'];
        yield 'FATAL' => ['FATAL'];
    }

    /** @return iterable<string, array{string}> */
    public static function nonStoppedStatuses(): iterable
    {
        yield 'RUNNING' => ['RUNNING'];
        yield 'STARTING' => ['STARTING'];
        yield 'BACKOFF' => ['BACKOFF'];
    }
}
