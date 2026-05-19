# Softer Messenger Monitor Bundle

Symfony bundle that monitors Messenger queues by reading worker status directly from Supervisor. No cache, no heartbeats, no race conditions - just asks `supervisorctl` every time.

## What it does

- Gets worker status from `supervisorctl` with start/stop/restart control
- Counts pending messages per queue (Doctrine transport)
- Tracks message processing history: handled, failed, retried - with duration and memory usage
- Lists failed messages with retry/remove
- Auto-detects queue names from your Messenger transport config

Everything is available as PHP services, Twig functions, and JSON API. No UI - you build your own admin pages.

## Requirements

- PHP 8.2+
- Symfony 7.0+ / 8.0+
- Supervisor
- Doctrine DBAL (for queue stats and history)

## Installation

```bash
composer require softlab/messenger-monitor-bundle
```

## Configuration

Works out of the box with zero config. Queue names are auto-detected from your `framework.messenger.transports`, history table is created automatically on first message.

Override defaults only if you need to:

```yaml
# config/packages/softlab_messenger_monitor.yaml
softlab_messenger_monitor:
    supervisor:
        supervisorctl_path: supervisorctl   # path to binary
        process_group: ~                    # filter by group (null = all)
    queue:
        connection: default                 # Doctrine DBAL connection
        table_name: messenger_messages
    history:
        enabled: true                       # message processing history
        table_name: messenger_monitor_history
```

## Usage

### PHP services

```php
use SoftLab\MessengerMonitorBundle\Supervisor\SupervisorManagerInterface;
use SoftLab\MessengerMonitorBundle\Queue\QueueStatsProviderInterface;
use SoftLab\MessengerMonitorBundle\Failed\FailedMessageManager;
use SoftLab\MessengerMonitorBundle\History\MessageHistoryProviderInterface;

class DashboardController
{
    public function index(
        SupervisorManagerInterface $supervisor,
        QueueStatsProviderInterface $queueStats,
        FailedMessageManager $failedMessages,
        MessageHistoryProviderInterface $history,
    ): Response {
        $workers = $supervisor->getWorkers();       // WorkerInfo[]
        $queues = $queueStats->getQueues();         // QueueInfo[]
        $failed = $failedMessages->list();          // FailedMessage[]

        $entries = $history->getHistory(50);         // MessageHistoryEntry[]
        $stats = $history->getStats();              // ['handled' => N, 'failed' => N, 'retried' => N]
        $byQueue = $history->getStatsByQueue();     // ['async' => ['handled' => N, ...], ...]
        // ...
    }
}
```

### Twig

Requires `symfony/twig-bundle`.

```twig
{% for worker in messenger_workers() %}
    {{ worker.name }}: {{ worker.status }} (PID {{ worker.pid }})
{% endfor %}

{% for queue in messenger_queues() %}
    {{ queue.name }}: {{ queue.messageCount }} pending
{% endfor %}

{{ messenger_failed_count() }} failed messages
{{ messenger_total_pending() }} messages in queues

{% set stats = messenger_history_stats() %}
Handled: {{ stats.handled }}, Failed: {{ stats.failed }}, Retried: {{ stats.retried }}

{% for entry in messenger_history(20) %}
    {{ entry.shortClass }}: {{ entry.status }} ({{ entry.durationMs }}ms, {{ entry.memoryBytes }} bytes)
{% endfor %}
```

### JSON API

Import routes in your app:

```yaml
# config/routes/softlab_messenger_monitor.yaml
softlab_messenger_monitor:
    resource: '@SoftLabMessengerMonitorBundle/config/routes.php'
```

| Method | Path | Description |
|---|---|---|
| GET | `/summary` | Workers, queues, failed count |
| GET | `/workers` | Supervisor worker list |
| POST | `/workers/{name}/start` | Start worker |
| POST | `/workers/{name}/stop` | Stop worker |
| POST | `/workers/{name}/restart` | Restart worker |
| GET | `/queues` | Pending messages per queue |
| GET | `/failed` | Failed messages |
| POST | `/failed/{id}/retry` | Retry failed message |
| DELETE | `/failed/{id}` | Remove failed message |
| GET | `/history` | Processing history with stats |

All paths are relative to `/api/messenger-monitor`.

## Message history

Every processed message is recorded with:
- message class and queue name
- status: `handled`, `failed`, or `retried`
- processing duration (ms) and memory delta (bytes)
- error message (for failed/retried)

The `messenger_monitor_history` table is created automatically when the first message is processed. Disable with `history.enabled: false` if you don't need it.

## Demo

The `demo/` directory has a working Symfony app with Supervisor workers in Docker. Three panels: message producer, worker status with controls, queue stats. History panel with status and queue filters.

```bash
docker compose run --rm demo composer install
docker compose up demo
# http://localhost:8080
```

## License

MIT
