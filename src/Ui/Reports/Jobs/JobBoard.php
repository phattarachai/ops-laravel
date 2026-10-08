<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Phattarachai\OpsCore\Health\Health;
use Phattarachai\OpsCore\Health\HealthClassifier;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\TimeRange;
use stdClass;

/**
 * One row per job class over a window, worst first: runs, failed (failed + timed out), released
 * (kept out of every duration figure), avg / p95 duration, queue wait p95, the queues it ran on
 * and two sparkline series.
 */
final class JobBoard
{
    private const int SPARK_POINTS = 20;

    /** Newest run in the window — the TimeFilter's freshness stamp. */
    public static function dataUpdatedAt(TimeRange $range): ?CarbonImmutable
    {
        $latest = JobRun::query()->where('started_at', '>=', $range->start)->max('started_at');

        return $latest === null ? null : CarbonImmutable::parse((string) $latest);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(TimeRange $range): array
    {
        return OpsCache::remember("jobs:board:{$range->key}", fn (): array => self::freshRows($range));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function row(string $jobClass, TimeRange $range): ?array
    {
        return collect(self::rows($range))->firstWhere('job_class', $jobClass);
    }

    /**
     * @return array{health: string, health_label: string, health_color: string}
     */
    public static function health(Health $health): array
    {
        return [
            'health' => $health->value,
            'health_label' => match ($health) {
                Health::Incident => 'Failing',
                Health::Degraded => 'Degrading',
                Health::NoData => 'No data',
                default => 'Healthy',
            },
            'health_color' => $health->tone(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function freshRows(TimeRange $range): array
    {
        $sql = RunSql::make();
        $queues = self::queues($range);
        $series = self::series($range, $sql);

        return self::inWindow($range)
            ->selectRaw(implode(', ', [
                'job_class',
                'count(*) as runs',
                RunSql::countWhere(RunSql::FAILED).' as failed',
                RunSql::countWhere("status = 'released'").' as released',
                RunSql::avgWhere('duration_ms', RunSql::TIMED).' as avg_ms',
                'max(started_at) as last_run_at',
                $sql->percentiles('duration', 'duration_ms', RunSql::TIMED, [0.95]),
                $sql->percentiles('wait', 'wait_ms', 'wait_ms is not null', [0.95]),
            ]))
            ->groupBy('job_class')
            ->get()
            ->map(fn (object $row): array => self::decorate($row, $sql, $queues->get($row->job_class), $series[$row->job_class] ?? ['outcome' => [], 'p95' => []]))
            ->sortByDesc(fn (array $row): array => [$row['failed'], $row['runs']])
            ->values()
            ->all();
    }

    /**
     * @param  array{queue: string, queues: list<string>, connection: ?string}|null  $queues
     * @param  array{outcome: list<array{ok: int, timeout: int}>, p95: list<?float>}  $series
     * @return array<string, mixed>
     */
    private static function decorate(object $row, RunSql $sql, ?array $queues, array $series): array
    {
        $runs = (int) $row->runs;
        $failed = (int) $row->failed;
        $avgMs = (int) round((float) ($row->avg_ms ?? 0));
        $p95Ms = (int) round($sql->percentile($row, 'duration', 0.95) ?? 0);
        $waitMs = $sql->percentile($row, 'wait', 0.95);
        $lastRun = CarbonImmutable::parse((string) $row->last_run_at);
        $health = new HealthClassifier()->forFailureRate($runs, $failed);
        $queueNames = $queues['queues'] ?? ['default'];

        return JobIdentity::of($row->job_class) + [
            'queue' => $queues['queue'] ?? 'default',
            'more_queues' => max(0, count($queueNames) - 1),
            'queues' => $queueNames,
            'connection' => $queues['connection'] ?? '—',
            'runs' => $runs,
            'failed' => $failed,
            'released' => (int) $row->released,
            'fail_pct' => $runs > 0 ? round($failed / $runs * 100, 1) : 0.0,
            'fail_label' => $failed > 0 ? "{$failed} / {$runs}" : '0',
            'avg_ms' => $avgMs,
            'avg_label' => Format::duration($avgMs / 1000),
            'p95_ms' => $p95Ms,
            'p95_label' => Format::duration($p95Ms / 1000),
            'wait_p95_ms' => $waitMs === null ? null : (int) round($waitMs),
            'wait_p95_label' => Format::duration($waitMs === null ? null : $waitMs / 1000),
            'last_run_at' => Format::date($lastRun),
            'last_run_at_iso' => $lastRun->toIso8601String(),
            ...self::health($health),
            'spark_outcome' => $series['outcome'],
            'spark_p95' => $series['p95'],
        ];
    }

    /**
     * The most-used queue and connection of each class, with every queue it ran on.
     *
     * @return Collection<array-key, array{queue: string, queues: list<string>, connection: ?string}>
     */
    private static function queues(TimeRange $range): Collection
    {
        return self::inWindow($range)
            ->selectRaw("job_class, coalesce(queue, 'default') as queue_name, connection, count(*) as runs")
            ->groupBy('job_class', 'queue_name', 'connection')
            ->get()
            ->groupBy('job_class')
            ->map(fn (Collection $rows): array => [
                'queue' => (string) $rows->sortBy('queue_name')->sortByDesc('runs')->first()->queue_name,
                'queues' => array_values($rows->pluck('queue_name')->map(strval(...))->unique()->sort()->all()),
                'connection' => self::nullableString($rows->sortByDesc('runs')->first()->connection),
            ]);
    }

    /**
     * Per class: an outcome mix (ok / failed in the red `timeout` slot) and a p95 line over
     * {@see self::SPARK_POINTS} equal slots of the window, keeping only the slots that ran.
     *
     * @return array<string, array{outcome: list<array{ok: int, timeout: int}>, p95: list<?float>}>
     */
    private static function series(TimeRange $range, RunSql $sql): array
    {
        $span = max(60, (int) abs($range->start->diffInSeconds($range->end)));
        $step = (int) ceil($span / self::SPARK_POINTS);

        return self::inWindow($range)
            ->selectRaw(implode(', ', [
                'job_class',
                $sql->slot('started_at', $range->start, $step).' as slot',
                'count(*) as runs',
                RunSql::countWhere(RunSql::FAILED).' as failed',
                $sql->percentiles('duration', 'duration_ms', RunSql::TIMED, [0.95]),
            ]))
            ->groupBy('job_class', 'slot')
            ->get()
            ->groupBy('job_class')
            ->map(fn (Collection $slots): array => self::sparkSeries($slots->sortBy(fn (object $slot): int => (int) $slot->slot), $sql))
            ->all();
    }

    /**
     * @param  Collection<int, stdClass>  $slots
     * @return array{outcome: list<array{ok: int, timeout: int}>, p95: list<?float>}
     */
    private static function sparkSeries(Collection $slots, RunSql $sql): array
    {
        return [
            'outcome' => $slots->map(fn (object $slot): array => ['ok' => (int) $slot->runs - (int) $slot->failed, 'timeout' => (int) $slot->failed])->values()->all(),
            'p95' => $slots->map(fn (object $slot): float => round(($sql->percentile($slot, 'duration', 0.95) ?? 0) / 1000, 3))->values()->all(),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function inWindow(TimeRange $range): Builder
    {
        return JobRun::query()
            ->where('started_at', '>=', $range->start)
            ->where('started_at', '<', $range->end)
            ->toBase();
    }
}
