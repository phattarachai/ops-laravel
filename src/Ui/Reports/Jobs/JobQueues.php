<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Jobs;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\TimeRange;
use stdClass;

/**
 * The Workers & queues view: Horizon's effective supervisors (`horizon.defaults` merged with the
 * current environment) with each queue's runs in the window. Without Horizon every queue that ran
 * lands in the supervisor-less group.
 */
final class JobQueues
{
    public static function hasHorizon(): bool
    {
        return is_array(config('horizon.defaults')) || is_array(config('horizon.environments'));
    }

    /**
     * @return list<array{name: string, connection: string, queues: list<string>, max_processes: int, balance: string, timeout: int}>
     */
    public static function supervisors(): array
    {
        $plan = array_replace_recursive((array) config('horizon.defaults', []), (array) config('horizon.environments.'.app()->environment(), []));

        return collect($plan)
            ->filter(fn (mixed $config): bool => is_array($config))
            ->map(fn (array $config, int|string $name): array => [
                'name' => (string) $name,
                'connection' => (string) ($config['connection'] ?? 'redis'),
                'queues' => array_values(array_map(strval(...), (array) ($config['queue'] ?? ['default']))),
                'max_processes' => (int) ($config['maxProcesses'] ?? 0),
                'balance' => (string) ($config['balance'] ?? 'off'),
                'timeout' => (int) ($config['timeout'] ?? 60),
            ])
            ->values()
            ->all();
    }

    public static function timeoutFor(string $queue): ?int
    {
        $supervisor = collect(self::supervisors())->first(fn (array $supervisor): bool => in_array($queue, $supervisor['queues'], true));

        return $supervisor['timeout'] ?? null;
    }

    /**
     * @return list<array{name: ?string, connection: ?string, max_processes: ?int, balance: ?string, timeout: ?int, queues: list<array<string, mixed>>}>
     */
    public static function board(TimeRange $range): array
    {
        $stats = collect(OpsCache::remember("jobs:queues:{$range->key}", fn (): array => self::stats($range)));
        $supervisors = self::supervisors();
        $drained = collect($supervisors)->pluck('queues')->flatten()->unique();

        $groups = array_map(fn (array $supervisor): array => [
            ...$supervisor,
            'queues' => array_map(
                fn (string $queue, int $priority): array => ['queue' => $queue, 'priority' => $priority + 1, ...($stats->get($queue) ?? self::emptyStats())],
                $supervisor['queues'],
                array_keys($supervisor['queues']),
            ),
        ], $supervisors);

        $orphans = $stats->reject(fn (array $row, string $queue): bool => $drained->contains($queue));

        if ($orphans->isEmpty()) {
            return $groups;
        }

        return [...$groups, [
            'name' => null,
            'connection' => null,
            'max_processes' => null,
            'balance' => null,
            'timeout' => null,
            'queues' => $orphans->map(fn (array $row, string $queue): array => ['queue' => $queue, 'priority' => null, ...$row])->values()->all(),
        ]];
    }

    /**
     * @return array<string, array{runs: int, failed: int, released: int, jobs: int, top_jobs: list<string>, p95: string, p95_seconds: ?float, wait_p95: string, wait_p95_seconds: ?float}>
     */
    private static function stats(TimeRange $range): array
    {
        $percentiles = self::percentiles($range);

        return self::inWindow($range)
            ->selectRaw(implode(', ', [
                "coalesce(queue, 'default') as queue_name",
                'job_class',
                'count(*) as runs',
                RunSql::countWhere(RunSql::FAILED).' as failed',
                RunSql::countWhere("status = 'released'").' as released',
            ]))
            ->groupBy('queue_name', 'job_class')
            ->get()
            ->groupBy('queue_name')
            ->map(fn (Collection $classes, string $queue): array => self::queueRow($classes, $percentiles[$queue] ?? ['p95' => null, 'wait_p95' => null]))
            ->all();
    }

    /**
     * @param  Collection<int, stdClass>  $classes
     * @param  array{p95: ?float, wait_p95: ?float}  $percentiles
     * @return array{runs: int, failed: int, released: int, jobs: int, top_jobs: list<string>, p95: string, p95_seconds: ?float, wait_p95: string, wait_p95_seconds: ?float}
     */
    private static function queueRow(Collection $classes, array $percentiles): array
    {
        return [
            'runs' => (int) $classes->sum('runs'),
            'failed' => (int) $classes->sum('failed'),
            'released' => (int) $classes->sum('released'),
            'jobs' => $classes->count(),
            'top_jobs' => $classes->sortByDesc('runs')->take(4)->map(fn (object $row): string => class_basename((string) $row->job_class))->values()->all(),
            'p95' => Format::duration($percentiles['p95']),
            'p95_seconds' => $percentiles['p95'] === null ? null : round($percentiles['p95'], 1),
            'wait_p95' => Format::duration($percentiles['wait_p95']),
            'wait_p95_seconds' => $percentiles['wait_p95'] === null ? null : round($percentiles['wait_p95'], 1),
        ];
    }

    /**
     * @return array<string, array{p95: ?float, wait_p95: ?float}> seconds per queue
     */
    private static function percentiles(TimeRange $range): array
    {
        $sql = RunSql::make();

        return self::inWindow($range)
            ->selectRaw(implode(', ', [
                "coalesce(queue, 'default') as queue_name",
                $sql->percentiles('duration', 'duration_ms', RunSql::TIMED, [0.95]),
                $sql->percentiles('wait', 'wait_ms', 'wait_ms is not null', [0.95]),
            ]))
            ->groupBy('queue_name')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->queue_name => [
                'p95' => self::toSeconds($sql->percentile($row, 'duration', 0.95)),
                'wait_p95' => self::toSeconds($sql->percentile($row, 'wait', 0.95)),
            ]])
            ->all();
    }

    private static function toSeconds(?float $milliseconds): ?float
    {
        return $milliseconds === null ? null : $milliseconds / 1000;
    }

    /**
     * @return array{runs: int, failed: int, released: int, jobs: int, top_jobs: list<string>, p95: string, p95_seconds: ?float, wait_p95: string, wait_p95_seconds: ?float}
     */
    private static function emptyStats(): array
    {
        return ['runs' => 0, 'failed' => 0, 'released' => 0, 'jobs' => 0, 'top_jobs' => [], 'p95' => '—', 'p95_seconds' => null, 'wait_p95' => '—', 'wait_p95_seconds' => null];
    }

    private static function inWindow(TimeRange $range): Builder
    {
        return JobRun::query()
            ->where('started_at', '>=', $range->start)
            ->where('started_at', '<', $range->end)
            ->toBase();
    }
}
