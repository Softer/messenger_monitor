<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Tests\Supervisor;

use PHPUnit\Framework\TestCase;
use SoftLab\MessengerMonitorBundle\Supervisor\HttpSupervisorManager;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class HttpSupervisorManagerTest extends TestCase
{
    public function testGetWorkersReturnsAllProcesses(): void
    {
        $xml = $this->wrapResponse('
            <array><data>
                <value><struct>
                    <member><name>name</name><value><string>async_worker_00</string></value></member>
                    <member><name>group</name><value><string>messenger</string></value></member>
                    <member><name>state</name><value><int>20</int></value></member>
                    <member><name>pid</name><value><int>1234</int></value></member>
                    <member><name>start</name><value><int>' . (time() - 5445) . '</int></value></member>
                    <member><name>description</name><value><string>pid 1234, uptime 1:30:45</string></value></member>
                </struct></value>
                <value><struct>
                    <member><name>name</name><value><string>orders_worker_00</string></value></member>
                    <member><name>group</name><value><string>messenger</string></value></member>
                    <member><name>state</name><value><int>0</int></value></member>
                    <member><name>pid</name><value><int>0</int></value></member>
                    <member><name>start</name><value><int>0</int></value></member>
                    <member><name>description</name><value><string>Not started</string></value></member>
                </struct></value>
            </data></array>
        ');

        $manager = $this->createManager($xml);
        $workers = $manager->getWorkers();

        self::assertCount(2, $workers);

        self::assertSame('async_worker_00', $workers[0]->name);
        self::assertSame('messenger', $workers[0]->group);
        self::assertSame('RUNNING', $workers[0]->status);
        self::assertSame(1234, $workers[0]->pid);
        self::assertNotNull($workers[0]->uptime);
        self::assertEqualsWithDelta(5445, $workers[0]->uptime, 2);

        self::assertSame('orders_worker_00', $workers[1]->name);
        self::assertSame('STOPPED', $workers[1]->status);
        self::assertNull($workers[1]->pid);
        self::assertNull($workers[1]->uptime);
    }

    public function testGetWorkersFiltersProcessGroup(): void
    {
        $xml = $this->wrapResponse('
            <array><data>
                <value><struct>
                    <member><name>name</name><value><string>worker_00</string></value></member>
                    <member><name>group</name><value><string>messenger</string></value></member>
                    <member><name>state</name><value><int>20</int></value></member>
                    <member><name>pid</name><value><int>100</int></value></member>
                    <member><name>start</name><value><int>' . (time() - 60) . '</int></value></member>
                    <member><name>description</name><value><string>running</string></value></member>
                </struct></value>
                <value><struct>
                    <member><name>name</name><value><string>job_runner</string></value></member>
                    <member><name>group</name><value><string>cron</string></value></member>
                    <member><name>state</name><value><int>20</int></value></member>
                    <member><name>pid</name><value><int>200</int></value></member>
                    <member><name>start</name><value><int>' . (time() - 120) . '</int></value></member>
                    <member><name>description</name><value><string>running</string></value></member>
                </struct></value>
            </data></array>
        ');

        $manager = $this->createManager($xml, processGroup: 'messenger');
        $workers = $manager->getWorkers();

        self::assertCount(1, $workers);
        self::assertSame('worker_00', $workers[0]->name);
    }

    public function testGetWorkersReturnsEmptyOnError(): void
    {
        $manager = $this->createManager(null);

        self::assertSame([], $manager->getWorkers());
    }

    public function testGetWorkersReturnsEmptyOnFault(): void
    {
        $xml = '<?xml version="1.0"?><methodResponse><fault><value><struct>'
            . '<member><name>faultCode</name><value><int>1</int></value></member>'
            . '<member><name>faultString</name><value><string>error</string></value></member>'
            . '</struct></value></fault></methodResponse>';

        $manager = $this->createManager($xml);

        self::assertSame([], $manager->getWorkers());
    }

    public function testGetWorkerReturnsSingleProcess(): void
    {
        $xml = $this->wrapResponse('
            <struct>
                <member><name>name</name><value><string>async_worker_00</string></value></member>
                <member><name>group</name><value><string>messenger</string></value></member>
                <member><name>state</name><value><int>20</int></value></member>
                <member><name>pid</name><value><int>1234</int></value></member>
                <member><name>start</name><value><int>' . (time() - 600) . '</int></value></member>
                <member><name>description</name><value><string>pid 1234, uptime 0:10:00</string></value></member>
            </struct>
        ');

        $manager = $this->createManager($xml);
        $worker = $manager->getWorker('messenger:async_worker_00');

        self::assertNotNull($worker);
        self::assertSame('async_worker_00', $worker->name);
        self::assertSame('messenger', $worker->group);
        self::assertSame(1234, $worker->pid);
        self::assertTrue($worker->isRunning());
    }

    public function testGetWorkerReturnsNullOnError(): void
    {
        $manager = $this->createManager(null);

        self::assertNull($manager->getWorker('nonexistent'));
    }

    public function testStartWorkerReturnsTrue(): void
    {
        $xml = $this->wrapResponse('<boolean>1</boolean>');

        $manager = $this->createManager($xml);

        self::assertTrue($manager->startWorker('messenger:worker_00'));
    }

    public function testStartWorkerReturnsFalseOnError(): void
    {
        $manager = $this->createManager(null);

        self::assertFalse($manager->startWorker('test'));
    }

    public function testStopWorkerReturnsTrue(): void
    {
        $xml = $this->wrapResponse('<boolean>1</boolean>');

        $manager = $this->createManager($xml);

        self::assertTrue($manager->stopWorker('messenger:worker_00'));
    }

    public function testRestartWorkerCallsStopThenStart(): void
    {
        $calls = [];
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturnCallback(
            function (string $method, string $url, array $options) use (&$calls) {
                $calls[] = $options['body'];

                $response = $this->createMock(ResponseInterface::class);
                $response->method('getContent')->willReturn(
                    $this->wrapResponse('<boolean>1</boolean>')
                );

                return $response;
            }
        );

        $manager = new HttpSupervisorManager($client, 'http://localhost:9001/RPC2');
        $result = $manager->restartWorker('messenger:worker_00');

        self::assertTrue($result);
        self::assertCount(2, $calls);
        self::assertStringContainsString('supervisor.stopProcess', $calls[0]);
        self::assertStringContainsString('supervisor.startProcess', $calls[1]);
    }

    public function testRestartWorkerReturnsFalseIfStopFails(): void
    {
        $manager = $this->createManager(null);

        self::assertFalse($manager->restartWorker('test'));
    }

    public function testIsAvailableReturnsTrue(): void
    {
        $xml = $this->wrapResponse('<string>4.2.5</string>');

        $manager = $this->createManager($xml);

        self::assertTrue($manager->isAvailable());
    }

    public function testIsAvailableReturnsFalseOnError(): void
    {
        $manager = $this->createManager(null);

        self::assertFalse($manager->isAvailable());
    }

    public function testMapsAllSupervisorStates(): void
    {
        $states = [
            0 => 'STOPPED',
            10 => 'STARTING',
            20 => 'RUNNING',
            30 => 'BACKOFF',
            40 => 'STOPPING',
            100 => 'EXITED',
            200 => 'FATAL',
            1000 => 'UNKNOWN',
            999 => 'UNKNOWN',
        ];

        foreach ($states as $code => $expected) {
            $xml = $this->wrapResponse('
                <array><data><value><struct>
                    <member><name>name</name><value><string>w</string></value></member>
                    <member><name>group</name><value><string>g</string></value></member>
                    <member><name>state</name><value><int>' . $code . '</int></value></member>
                    <member><name>pid</name><value><int>0</int></value></member>
                    <member><name>start</name><value><int>0</int></value></member>
                    <member><name>description</name><value><string></string></value></member>
                </struct></value></data></array>
            ');

            $manager = $this->createManager($xml);
            $workers = $manager->getWorkers();

            self::assertSame($expected, $workers[0]->status, "State code $code should map to $expected");
        }
    }

    public function testRequestSendsCorrectXmlRpc(): void
    {
        $sentBody = null;
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturnCallback(
            function (string $method, string $url, array $options) use (&$sentBody) {
                self::assertSame('POST', $method);
                self::assertSame('http://localhost:9001/RPC2', $url);
                self::assertSame('text/xml', $options['headers']['Content-Type']);
                $sentBody = $options['body'];

                $response = $this->createMock(ResponseInterface::class);
                $response->method('getContent')->willReturn(
                    $this->wrapResponse('<boolean>1</boolean>')
                );

                return $response;
            }
        );

        $manager = new HttpSupervisorManager($client, 'http://localhost:9001/RPC2');
        $manager->startWorker('messenger:worker_00');

        self::assertNotNull($sentBody);
        self::assertStringContainsString('<methodName>supervisor.startProcess</methodName>', $sentBody);
        self::assertStringContainsString('<string>messenger:worker_00</string>', $sentBody);
    }

    public function testStoppedWorkerHasNoUptime(): void
    {
        $xml = $this->wrapResponse('
            <array><data><value><struct>
                <member><name>name</name><value><string>w</string></value></member>
                <member><name>group</name><value><string>g</string></value></member>
                <member><name>state</name><value><int>0</int></value></member>
                <member><name>pid</name><value><int>0</int></value></member>
                <member><name>start</name><value><int>0</int></value></member>
                <member><name>description</name><value><string>Not started</string></value></member>
            </struct></value></data></array>
        ');

        $manager = $this->createManager($xml);
        $workers = $manager->getWorkers();

        self::assertNull($workers[0]->uptime);
        self::assertTrue($workers[0]->isStopped());
    }

    public function testUnixSocketUrlIsDetected(): void
    {
        $manager = new HttpSupervisorManager(null, 'unix:///var/run/supervisor.sock');

        // Unix socket with non-existent path returns empty workers (not an exception)
        self::assertSame([], $manager->getWorkers());
        self::assertFalse($manager->isAvailable());
    }

    public function testUnixSocketStartReturnsFalseOnError(): void
    {
        $manager = new HttpSupervisorManager(null, 'unix:///nonexistent/supervisor.sock');

        self::assertFalse($manager->startWorker('test'));
        self::assertFalse($manager->stopWorker('test'));
    }

    public function testUnixSocketGetWorkerReturnsNullOnError(): void
    {
        $manager = new HttpSupervisorManager(null, 'unix:///nonexistent/supervisor.sock');

        self::assertNull($manager->getWorker('test'));
    }

    private function wrapResponse(string $valueXml): string
    {
        return '<?xml version="1.0"?><methodResponse><params><param><value>'
            . $valueXml
            . '</value></param></params></methodResponse>';
    }

    private function createManager(?string $responseXml, ?string $processGroup = null): HttpSupervisorManager
    {
        $client = $this->createMock(HttpClientInterface::class);

        if ($responseXml !== null) {
            $response = $this->createMock(ResponseInterface::class);
            $response->method('getContent')->willReturn($responseXml);
            $client->method('request')->willReturn($response);
        } else {
            $client->method('request')->willThrowException(new \RuntimeException('Connection refused'));
        }

        return new HttpSupervisorManager($client, 'http://localhost:9001/RPC2', $processGroup);
    }
}
