<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Schedules;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Phattarachai\OpsCore\Failures\Failure;
use Phattarachai\OpsLaravel\Enums\ScheduleRunStatus;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\IncidentRows;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\RunSql;
use Phattarachai\OpsLaravel\Ui\TimeRange;
use stdClass;

/** The schedule drill-in: runs by status per bar with durations, the latest runs with the jobs each dispatched, and failures. */
final class ScheduleDetail
{
    private const int RECENT_RUNS = 30;

    /**
     * Ran (`schedule`) and skipped (`other`) per bar, in the StackedBars / RangeBars shape.
     *
     * @return list<array{label: string, schedule: int, web: int, other: int, failed: int, released: int, p50: ?float, p95: ?float, max: ?float}>
     */
    public static function buckets(string $task, TimeRange $range): array
    {
        return self::remember("buckets:{$task}:{$range->key}", function () use ($task, $range): array {
            $sql = RunSql::make();
            $origin = $range->firstBarStart();

            $rows = ScheduleRun::query()
                ->where('task', $task)
                ->where('started_at', '>=', $origin)
                ->where('started_at', '<', $range->end)
                ->toBase()
                ->selectRaw(implode(', ', [
                    $sql->slot('started_at', $origin, $range->step * 60).' as slot',
                    RunSql::countWhere(RunSql::RAN).' as ran',
                    RunSql::countWhere("status = 'failed'").' as failed',
                    RunSql::countWhere("status = 'skipped'").' as skipped',
                    RunSql::maxWhere('duration_ms', RunSql::RAN).' as max_ms',
                    $sql->percentiles('duration', 'duration_ms', RunSql::RAN, [0.5, 0.95]),
                ]))
                ->groupBy('slot')
                ->get()
                ->keyBy(fn (object $row): int => (int) $row->slot);

            return array_map(function (int $index) use ($range, $rows, $sql): array {
                $row = $rows->get($index);

                return [
                    'label' => $range->barLabel($index),
                    'schedule' => (int) ($row->ran ?? 0),
                    'web' => 0,
                    'other' => (int) ($row->skipped ?? 0),
                    'failed' => (int) ($row->failed ?? 0),
                    'released' => 0,
                    'p50' => self::seconds($sql->percentile($row, 'duration', 0.5)),
                    'p95' => self::seconds($sql->percentile($row, 'duration', 0.95)),
                    'max' => self::seconds(isset($row->max_ms) ? (float) $row->max_ms : null),
                ];
            }, range(0, $range->bars - 1));
        });
    }

    /**
     * @return list<array{at: string, at_iso: string, status: string, duration: string, detail: ?string, jobs: int, jobs_failed: int, job_classes: list<string>}>
     */
    public static function recentRuns(string $task): array
    {
        return self::remember("runs:{$task}", function () use ($task): array {
            $runs = ScheduleRun::query()->where('task', $task)->latest('started_at')->latest('id')->limit(self::RECENT_RUNS)->get();
            $fanOut = self::fanOut($runs->pluck('id')->all());

            return $runs->map(fn (ScheduleRun $run): array => [
                'at' => (string) Format::date($run->started_at, 'j M H:i:s'),
                'at_iso' => $run->started_at->toIso8601String(),
                'status' => $run->status->value,
                'duration' => Format::duration($run->duration_ms === null ? null : $run->duration_ms / 1000),
                'detail' => $run->exception_message ?? ($run->exit_code !== null && $run->exit_code !== 0 ? "exit {$run->exit_code}" : null),
                'jobs' => (int) $fanOut->get($run->id, collect())->sum('jobs'),
                'jobs_failed' => (int) $fanOut->get($run->id, collect())->sum('failed'),
                'job_classes' => $fanOut->get($run->id, collect())->sortByDesc('jobs')->take(3)->map(fn (object $row): string => class_basename((string) $row->job_class))->values()->all(),
            ])->all();
        });
    }

    /**
     * @return list<array{at: string, at_iso: string, count: int, exception: string, message: string, duration: string, domains: list<string>, more_domains: int}>
     */
    public static function incidents(string $task): array
    {
        return self::remember("incidents:{$task}", fn (): array => IncidentRows::from(
            ScheduleRun::query()
                ->where('task', $task)
                ->where('status', ScheduleRunStatus::Failed->value)
                ->where('started_at', '>=', CarbonImmutable::now()->subDays(7))
                ->latest('started_at')
                ->limit(500)
                ->get()
                ->map(fn (ScheduleRun $run): Failure => new Failure(
                    $run->started_at,
                    $run->exception_class ?? 'ExitCode',
                    $run->exception_message ?? "exit {$run->exit_code}",
                    $run->duration_ms === null ? null : $run->duration_ms / 1000,
                )),
        ));
    }

    /**
     * @param  list<int>  $runIds
     * @return Collection<array-key, Collection<int, stdClass>>
     */
    private static function fanOut(array $runIds): Collection
    {
        return JobRun::query()
            ->whereIn('schedule_run_id', $runIds)
            ->toBase()
            ->selectRaw('schedule_run_id, job_class, count(*) as jobs, '.RunSql::countWhere(RunSql::FAILED).' as failed')
            ->groupBy('schedule_run_id', 'job_class')
            ->get()
            ->groupBy(fn (object $row): int => (int) $row->schedule_run_id);
    }

    private static function seconds(?float $milliseconds): ?float
    {
        return $milliseconds === null ? null : round($milliseconds / 1000, 3);
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private static function remember(string $key, callable $callback): mixed
    {
        return OpsCache::remember('schedules:detail:'.md5($key), $callback);
    }
}
