<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Samplers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Phattarachai\OpsLaravel\Models\QueueSample;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

/**
 * One depth sample per queue per minute (pending / delayed / reserved and the oldest pending
 * job's wait when the driver can tell). Queues come from the setting, else Horizon's
 * supervisors, else the default connection's queue.
 */
final readonly class QueueSampler
{
    public function __construct(private QueueFactory $queues, private OpsSettings $settings) {}

    public function sample(CarbonImmutable $minute): int
    {
        if (! $this->settings->bool('recorders.queues.enabled')) {
            return 0;
        }

        $rows = [];

        foreach ($this->targets() as [$connection, $queue]) {
            $row = rescue(fn (): array => $this->read($connection, $queue, $minute), null, report: false);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        if ($rows !== []) {
            QueueSample::query()->upsert($rows, ['bucket', 'connection', 'queue'], ['size', 'pending', 'delayed', 'reserved', 'oldest_wait_seconds']);
        }

        return count($rows);
    }

    /** @return list<array{0: string, 1: string}> */
    public function targets(): array
    {
        $configured = $this->settings->list('recorders.queues.queues');
        $connection = (string) config('queue.default');

        $queues = match (true) {
            $configured !== [] => $configured,
            is_array(config('horizon.defaults')) => $this->horizonQueues(),
            default => [(string) config("queue.connections.{$connection}.queue", 'default')],
        };

        return array_map(static fn (string $queue): array => str_contains($queue, ':') ? explode(':', $queue, 2) + [1 => 'default'] : [$connection, $queue], array_values(array_unique($queues)));
    }

    /** @return list<string> */
    private function horizonQueues(): array
    {
        $supervisors = array_replace_recursive((array) config('horizon.defaults', []), (array) config('horizon.environments.'.app()->environment(), []));
        $queues = [];

        foreach ($supervisors as $supervisor) {
            foreach ((array) ($supervisor['queue'] ?? []) as $queue) {
                $queues[] = (string) $queue;
            }
        }

        return $queues;
    }

    /** @return array{bucket: CarbonImmutable, connection: string, queue: string, size: int, pending: int|null, delayed: int|null, reserved: int|null, oldest_wait_seconds: int|null} */
    private function read(string $connection, string $queue, CarbonImmutable $minute): array
    {
        $driver = $this->queues->connection($connection);

        return [
            'bucket' => $minute,
            'connection' => $connection,
            'queue' => $queue,
            'size' => (int) $driver->size($queue),
            'pending' => $this->optional($driver, 'pendingSize', $queue),
            'delayed' => $this->optional($driver, 'delayedSize', $queue),
            'reserved' => $this->optional($driver, 'reservedSize', $queue),
            'oldest_wait_seconds' => $this->oldestWait($driver, $queue, $minute),
        ];
    }

    private function optional(Queue $driver, string $method, string $queue): ?int
    {
        return method_exists($driver, $method) ? (int) $driver->{$method}($queue) : null;
    }

    private function oldestWait(Queue $driver, string $queue, CarbonImmutable $minute): ?int
    {
        $created = rescue(fn (): mixed => $driver->creationTimeOfOldestPendingJob($queue), null, report: false);

        return is_numeric($created) ? max(0, $minute->getTimestamp() - (int) $created) : null;
    }
}
