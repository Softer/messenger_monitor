<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle\Supervisor;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpSupervisorManager implements SupervisorManagerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $url,
        private readonly ?string $processGroup = null,
    ) {
    }

    public function getWorkers(): array
    {
        $result = $this->call('supervisor.getAllProcessInfo');

        if (!\is_array($result)) {
            return [];
        }

        $workers = [];

        foreach ($result as $info) {
            $worker = $this->mapProcessInfo($info);

            if ($this->processGroup !== null && $worker->group !== $this->processGroup) {
                continue;
            }

            $workers[] = $worker;
        }

        return $workers;
    }

    public function getWorker(string $name): ?WorkerInfo
    {
        $result = $this->call('supervisor.getProcessInfo', [$name]);

        if (!\is_array($result)) {
            return null;
        }

        return $this->mapProcessInfo($result);
    }

    public function startWorker(string $name): bool
    {
        $result = $this->call('supervisor.startProcess', [$name]);

        return $result === true;
    }

    public function stopWorker(string $name): bool
    {
        $result = $this->call('supervisor.stopProcess', [$name]);

        return $result === true;
    }

    public function restartWorker(string $name): bool
    {
        if (!$this->stopWorker($name)) {
            return false;
        }

        return $this->startWorker($name);
    }

    public function isAvailable(): bool
    {
        $result = $this->call('supervisor.getVersion');

        return \is_string($result) && $result !== '';
    }

    /**
     * @param array<string, mixed> $info
     */
    private function mapProcessInfo(array $info): WorkerInfo
    {
        $name = (string) ($info['name'] ?? '');
        $group = (string) ($info['group'] ?? $name);
        $state = (int) ($info['state'] ?? 0);
        $pid = (int) ($info['pid'] ?? 0);
        $start = (int) ($info['start'] ?? 0);
        $description = (string) ($info['description'] ?? '');

        $status = match ($state) {
            0 => 'STOPPED',
            10 => 'STARTING',
            20 => 'RUNNING',
            30 => 'BACKOFF',
            40 => 'STOPPING',
            100 => 'EXITED',
            200 => 'FATAL',
            1000 => 'UNKNOWN',
            default => 'UNKNOWN',
        };

        $uptime = null;
        if ($status === 'RUNNING' && $start > 0) {
            $uptime = time() - $start;
        }

        return new WorkerInfo(
            name: $name,
            group: $group,
            status: $status,
            pid: $pid > 0 ? $pid : null,
            uptime: $uptime,
            description: $description !== '' ? $description : null,
        );
    }

    /**
     * @param list<string|int|bool> $params
     * @return string|bool|array<string|int, mixed>|null
     */
    private function call(string $method, array $params = []): string|bool|array|null
    {
        $xml = $this->buildRequest($method, $params);

        try {
            $response = $this->httpClient->request('POST', $this->url, [
                'headers' => ['Content-Type' => 'text/xml'],
                'body' => $xml,
                'timeout' => 10,
            ]);

            return $this->parseResponse($response->getContent());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param list<string|int|bool> $params
     */
    private function buildRequest(string $method, array $params): string
    {
        $xml = '<?xml version="1.0"?>';
        $xml .= '<methodCall>';
        $xml .= '<methodName>' . htmlspecialchars($method, \ENT_XML1) . '</methodName>';
        $xml .= '<params>';

        foreach ($params as $param) {
            $xml .= '<param><value>';

            if (\is_bool($param)) {
                $xml .= '<boolean>' . ($param ? '1' : '0') . '</boolean>';
            } elseif (\is_int($param)) {
                $xml .= '<int>' . $param . '</int>';
            } else {
                $xml .= '<string>' . htmlspecialchars((string) $param, \ENT_XML1) . '</string>';
            }

            $xml .= '</value></param>';
        }

        $xml .= '</params>';
        $xml .= '</methodCall>';

        return $xml;
    }

    /**
     * @return string|bool|array<string|int, mixed>|null
     */
    private function parseResponse(string $body): string|bool|array|null
    {
        $prev = libxml_use_internal_errors(true);

        try {
            $doc = simplexml_load_string($body);

            if ($doc === false) {
                return null;
            }

            if (isset($doc->fault)) {
                return null;
            }

            $value = $doc->params->param->value ?? null;

            if ($value === null) {
                return null;
            }

            return $this->parseValue($value);
        } finally {
            libxml_use_internal_errors($prev);
        }
    }

    /**
     * @return string|bool|int|array<string|int, mixed>|null
     */
    private function parseValue(\SimpleXMLElement $value): string|bool|int|array|null
    {
        if (isset($value->string)) {
            return (string) $value->string;
        }

        if (isset($value->boolean)) {
            return (string) $value->boolean === '1';
        }

        if (isset($value->int)) {
            return (int) (string) $value->int;
        }

        if (isset($value->i4)) {
            return (int) (string) $value->i4;
        }

        if (isset($value->array)) {
            $items = [];
            foreach ($value->array->data->value as $item) {
                $items[] = $this->parseValue($item);
            }
            return $items;
        }

        if (isset($value->struct)) {
            $map = [];
            foreach ($value->struct->member as $member) {
                $key = (string) $member->name;
                $map[$key] = $this->parseValue($member->value);
            }
            return $map;
        }

        // Plain text node (no type wrapper)
        $text = trim((string) $value);
        if ($text !== '') {
            return $text;
        }

        return null;
    }
}
