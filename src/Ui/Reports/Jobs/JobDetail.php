<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Jobs;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Phattarachai\OpsCore\Failures\Failure;
use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\TimeRange;

/** The job drill-in: per-bar runs by origin with durations, origins, sweeps, hour of day, histogram, failures and the latest runs. */
final class JobDetail
{
    /** @var list<string> */
    public const array ORIGINS = ['schedule', 'web', 'other'];

    private const int SWEEPS = 6;

    private const int RECENT_RUNS = 30;

    /**
     * @return list<array{label: string, schedule: int, web: int, other: int, failed: int, released: int, p50: ?float, p95: ?float, max: ?float}>
     */
    public static function buckets(string $jobClass, TimeRange $range): array
    {
        return self::remember("buckets:{$jobClass}:{$range->key}", function () use ($jobClass, $range): array {
            $sql = RunSql::make();
            $origin = $range->firstBarStart();
            $group = RunSql::ORIGIN_GROUP;

            $rows = self::runs($jobClass, $origin, $range->end)
                ->selectRaw(implode(', ', [
                    $sql->slot('started_at', $origin, $range->step * 60).' as slot',
                    RunSql::countWhere("{$group} = 'schedule'").' as schedule',
                    RunSql::countWhere("{$group} = 'web'").' as web',
                    RunSql::countWhere("{$group} = 'other'").' as other',
                    RunSql::countWhere(RunSql::FAILED).' as failed',
                    RunSql::countWhere("status = 'released'").' as released',
                    RunSql::maxWhere('duration_ms', RunSql::TIMED).' as max_ms',
                    $sql->percentiles('duration', 'duration_ms', RunSql::TIMED, [0.5, 0.95]),
                ]))
                ->groupBy('slot')
                ->get()
                ->keyBy(fn (object $row): int => (int) $row->slot);

            return array_map(fn (int $index): array => self::bucket($range->barLabel($index), $rows->get($index), $sql), range(0, $range->bars - 1));
        });
    }

    /**
     * @return list<array{key: string, runs: int, failed: int, fail_pct: float, released: int, p50: string, p95: string, wait_p95: ?string, chips: list<string>}>
     */
    public static function origins(string $jobClass, TimeRange $range): array
    {
        return self::remember("origins:{$jobClass}:{$range->key}", function () use ($jobClass, $range): array {
            $sql = RunSql::make();
            $group = RunSql::ORIGIN_GROUP;
            $queues = self::originQueues($jobClass, $range);

            return self::runs($jobClass, $range->start, $range->end)
                ->selectRaw(implode(', ', [
                    "{$group} as origin_group",
                    'count(*) as runs',
                    RunSql::countWhere(RunSql::FAILED).' as failed',
                    RunSql::countWhere("status = 'released'").' as released',
                    $sql->percentiles('duration', 'duration_ms', RunSql::TIMED, [0.5, 0.95]),
                    $sql->percentiles('wait', 'wait_ms', 'wait_ms is not null', [0.95]),
                ]))
                ->groupByRaw($group)
                ->get()
                ->sortBy(fn (object $row): int|false => array_search($row->origin_group, self::ORIGINS, true))
                ->map(fn (object $row): array => [
                    'key' => (string) $row->origin_group,
                    'runs' => (int) $row->runs,
                    'failed' => (int) $row->failed,
                    'fail_pct' => (int) $row->runs > 0 ? round((int) $row->failed / (int) $row->runs * 100, 1) : 0.0,
                    'released' => (int) $row->released,
                    'p50' => self::seconds($sql->percentile($row, 'duration', 0.5)),
                    'p95' => self::seconds($sql->percentile($row, 'duration', 0.95)),
                    'wait_p95' => self::optionalSeconds($sql->percentile($row, 'wait', 0.95)),
                    'chips' => $queues->get($row->origin_group, []),
                ])
                ->values()
                ->all();
        });
    }

    /**
     * The newest scheduled runs that fanned this job out, oldest of them first.
     *
     * @return list<array{id: int, title: string, day: string, task: string, runs: int, failed: int, minutes: float, p50: string, span: string}>
     */
    public static function sweeps(string $jobClass, TimeRange $range): array
    {
        return self::remember("sweeps:{$jobClass}:{$range->key}", function () use ($jobClass, $range): array {
            $sql = RunSql::make();

            $sweeps = self::runs($jobClass, $range->start, $range->end)
                ->whereNotNull('schedule_run_id')
                ->selectRaw(implode(', ', [
                    'schedule_run_id',
                    'count(*) as runs',
                    RunSql::countWhere(RunSql::FAILED).' as failed',
                    'min(started_at) as first_start',
                    'max(finished_at) as last_finish',
                    $sql->percentiles('duration', 'duration_ms', RunSql::TIMED, [0.5]),
                ]))
                ->groupBy('schedule_run_id')
                ->orderByDesc('first_start')
                ->limit(self::SWEEPS)
                ->get();

            $tasks = ScheduleRun::query()->whereIn('id', $sweeps->pluck('schedule_run_id'))->pluck('task', 'id');

            return $sweeps
                ->map(fn (object $sweep): array => self::sweep($sweep, (string) ($tasks[$sweep->schedule_run_id] ?? ''), $sql))
                ->reverse()
                ->values()
                ->all();
        });
    }

    /**
     * Seven days folded by hour of day.
     *
     * @return list<array{runs: int, failed: int}>
     */
    public static function hourOfDay(string $jobClass): array
    {
        return self::remember("hours:{$jobClass}", function () use ($jobClass): array {
            $since = CarbonImmutable::now()->subDays(7);
            $midnight = $since->startOfDay();

            $byHour = self::runs($jobClass, $since, CarbonImmutable::now())
                ->selectRaw(RunSql::make()->slot('started_at', $midnight, 3600).' as slot, count(*) as runs, '.RunSql::countWhere(RunSql::FAILED).' as failed')
                ->groupBy('slot')
                ->get()
                ->groupBy(fn (object $row): int => (int) $row->slot % 24);

            return array_map(fn (int $hour): array => [
                'runs' => (int) $byHour->get($hour, collect())->sum('runs'),
                'failed' => (int) $byHour->get($hour, collect())->sum('failed'),
            ], range(0, 23));
        });
    }

    /**
     * @return list<array{label: string, count: int, failed: int}>
     */
    public static function histogram(string $jobClass, TimeRange $range): array
    {
        return self::remember("histogram:{$jobClass}:{$range->key}", function () use ($jobClass, $range): array {
            $band = RunSql::band('duration_ms');

            $rows = self::runs($jobClass, $range->start, $range->end)
                ->whereRaw(RunSql::TIMED)
                ->whereNotNull('duration_ms')
                ->selectRaw("{$band} as band, count(*) as count, ".RunSql::countWhere(RunSql::FAILED).' as failed')
                ->groupByRaw($band)
                ->get()
                ->keyBy(fn (object $row): int => (int) $row->band);

            return array_map(fn (int $index): array => [
                'label' => self::bandLabel($index),
                'count' => (int) ($rows->get($index)->count ?? 0),
                'failed' => (int) ($rows->get($index)->failed ?? 0),
            ], range(0, LatencyHistogram::BIN_COUNT - 1));
        });
    }

    /**
     * @return list<array{at: string, at_iso: string, count: int, exception: string, message: string, duration: string, domains: list<string>, more_domains: int}>
     */
    public static function incidents(string $jobClass): array
    {
        return self::remember("incidents:{$jobClass}", fn (): array => IncidentRows::from(
            JobRun::query()
                ->where('job_class', $jobClass)
                ->where('started_at', '>=', CarbonImmutable::now()->subDays(7))
                ->whereIn('status', ['failed', 'timed_out'])
                ->latest('started_at')
                ->limit(500)
                ->get(['started_at', 'exception_class', 'exception_message', 'duration_ms', 'domain_id'])
                ->map(fn (JobRun $run): Failure => new Failure(
                    $run->started_at,
                    $run->exception_class,
                    $run->exception_message,
                    $run->duration_ms === null ? null : $run->duration_ms / 1000,
                    $run->domain_id,
                )),
        ));
    }

    /**
     * The latest runs regardless of the window.
     *
     * @return list<array{at: string, at_iso: string, status: string, duration: string, seconds: ?float, wait: string, wait_seconds: ?float, origin: ?string, queue: ?string, attempts: int, domain: ?string, error: ?string}>
     */
    public static function recentRuns(string $jobClass): array
    {
        return self::remember("recent:{$jobClass}", fn (): array => JobRun::query()
            ->where('job_class', $jobClass)
            ->latest('started_at')
            ->latest('id')
            ->limit(self::RECENT_RUNS)
            ->get()
            ->map(fn (JobRun $run): array => [
                'at' => (string) Format::date($run->started_at, 'j M H:i:s'),
                'at_iso' => $run->started_at->toIso8601String(),
                'status' => $run->status->value,
                'duration' => Format::duration($run->duration_ms === null ? null : $run->duration_ms / 1000),
                'seconds' => $run->duration_ms === null ? null : round($run->duration_ms / 1000, 3),
                'wait' => Format::duration($run->wait_ms === null ? null : $run->wait_ms / 1000),
                'wait_seconds' => $run->wait_ms === null ? null : round($run->wait_ms / 1000, 3),
                'origin' => $run->origin,
                'queue' => $run->queue,
                'attempts' => $run->attempts,
                'domain' => $run->domain_id === null ? null : class_basename((string) $run->domain_type).' #'.$run->domain_id,
                'error' => $run->exception_class === null ? null : class_basename($run->exception_class).': '.Str::limit((string) $run->exception_message, 160),
            ])
            ->all());
    }

    /**
     * @return array{label: string, schedule: int, web: int, other: int, failed: int, released: int, p50: ?float, p95: ?float, max: ?float}
     */
    private static function bucket(string $label, ?object $row, RunSql $sql): array
    {
        return [
            'label' => $label,
            'schedule' => (int) ($row->schedule ?? 0),
            'web' => (int) ($row->web ?? 0),
            'other' => (int) ($row->other ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'released' => (int) ($row->released ?? 0),
            'p50' => self::secondsOrNull($sql->percentile($row, 'duration', 0.5)),
            'p95' => self::secondsOrNull($sql->percentile($row, 'duration', 0.95)),
            'max' => self::secondsOrNull(isset($row->max_ms) ? (float) $row->max_ms : null),
        ];
    }

    /**
     * @return array{id: int, title: string, day: string, task: string, runs: int, failed: int, minutes: float, p50: string, span: string}
     */
    private static function sweep(object $sweep, string $task, RunSql $sql): array
    {
        $start = CarbonImmutable::parse((string) $sweep->first_start);
        $finish = CarbonImmutable::parse((string) ($sweep->last_finish ?? $sweep->first_start));

        return [
            'id' => (int) $sweep->schedule_run_id,
            'title' => $start->format('H:i'),
            'day' => (string) Format::date($start, 'j M'),
            'task' => class_basename($task),
            'runs' => (int) $sweep->runs,
            'failed' => (int) $sweep->failed,
            'minutes' => round(abs($start->diffInSeconds($finish)) / 60, 1),
            'p50' => self::seconds($sql->percentile($sweep, 'duration', 0.5)),
            'span' => $start->format('H:i:s').' → '.$finish->format('H:i:s'),
        ];
    }

    /**
     * @return Collection<array-key, list<string>>
     */
    private static function originQueues(string $jobClass, TimeRange $range): Collection
    {
        $group = RunSql::ORIGIN_GROUP;

        return self::runs($jobClass, $range->start, $range->end)
            ->whereNotNull('queue')
            ->selectRaw("{$group} as origin_group, queue")
            ->groupByRaw("{$group}, queue")
            ->orderBy('queue')
            ->get()
            ->groupBy('origin_group')
            ->map(fn (Collection $rows): array => array_values($rows->pluck('queue')->map(strval(...))->all()));
    }

    private static function bandLabel(int $index): string
    {
        $bounds = LatencyHistogram::BOUNDS;

        return $index < count($bounds) ? '<'.Format::duration($bounds[$index]) : '≥'.Format::duration($bounds[count($bounds) - 1]);
    }

    private static function seconds(?float $milliseconds): string
    {
        return Format::duration($milliseconds === null ? null : $milliseconds / 1000);
    }

    private static function optionalSeconds(?float $milliseconds): ?string
    {
        return $milliseconds === null ? null : self::seconds($milliseconds);
    }

    private static function secondsOrNull(?float $milliseconds): ?float
    {
        return $milliseconds === null ? null : round($milliseconds / 1000, 3);
    }

    private static function runs(string $jobClass, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return JobRun::query()
            ->where('job_class', $jobClass)
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $to)
            ->toBase();
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private static function remember(string $key, callable $callback): mixed
    {
        return OpsCache::remember('jobs:detail:'.md5($key), $callback);
    }
}
