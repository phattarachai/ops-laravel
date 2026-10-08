<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use stdClass;

/** The 14-day job × day matrix (closed days cached per date) and one day's job × hour grid. */
final class JobTimeline
{
    /**
     * @return array<string, list<array{date: string, runs: int, failed: int, released: int, max: ?float, status: string}>>
     */
    public static function history(int $days = Days::HISTORY): array
    {
        $today = CarbonImmutable::today();

        return OpsCache::remember("jobs:history:{$days}:{$today->toDateString()}", function () use ($days, $today): array {
            $dates = array_column(Days::history($days), 'date');
            $closed = OpsCache::rememberClosedDays("jobs:history:{$days}", fn (): array => self::dailyRows($today->subDays($days - 1), $today));

            return collect([...$closed, ...self::dailyRows($today, $today->addDay())])
                ->groupBy('job_class')
                ->map(fn (Collection $rows): array => self::cells($rows->keyBy('date'), $dates))
                ->all();
        });
    }

    /**
     * @return list<array{date: string, runs: int, failed: int, released: int, max: ?float, status: string}>
     */
    public static function idle(int $days = Days::HISTORY): array
    {
        return self::cells(collect(), array_column(Days::history($days), 'date'));
    }

    /**
     * @return array{hours: list<array{runs: int, failed: int, released: int, max: ?float, wait_p95: ?float}>, jobs: array<string, list<array{runs: int, failed: int, released: int, max: ?float, wait_p95: ?float}>>}
     */
    public static function day(CarbonImmutable $date): array
    {
        return OpsCache::remember("jobs:day:{$date->toDateString()}", function () use ($date): array {
            $sql = RunSql::make();

            return [
                'hours' => self::hourRow(self::hourly($date, $sql)->get(), $sql),
                'jobs' => self::hourly($date, $sql)
                    ->addSelect('job_class')
                    ->groupBy('job_class')
                    ->get()
                    ->groupBy('job_class')
                    ->map(fn (Collection $rows): array => self::hourRow($rows, $sql))
                    ->all(),
            ];
        });
    }

    /**
     * @return list<array{job_class: string, date: string, runs: int, failed: int, released: int, max: ?float}>
     */
    private static function dailyRows(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return JobRun::query()
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $until)
            ->toBase()
            ->selectRaw(implode(', ', [
                'job_class',
                RunSql::make()->slot('started_at', $from, 86400).' as day_index',
                'count(*) as runs',
                RunSql::countWhere(RunSql::FAILED).' as failed',
                RunSql::countWhere("status = 'released'").' as released',
                RunSql::maxWhere('duration_ms', RunSql::TIMED).' as max_ms',
            ]))
            ->groupBy('job_class', 'day_index')
            ->get()
            ->map(fn (object $row): array => [
                'job_class' => (string) $row->job_class,
                'date' => $from->addDays((int) $row->day_index)->toDateString(),
                'runs' => (int) $row->runs,
                'failed' => (int) $row->failed,
                'released' => (int) $row->released,
                'max' => $row->max_ms === null ? null : round((float) $row->max_ms / 1000, 1),
            ])
            ->all();
    }

    /**
     * @param  Collection<string, array{job_class: string, date: string, runs: int, failed: int, released: int, max: ?float}>  $byDate
     * @param  list<string>  $dates
     * @return list<array{date: string, runs: int, failed: int, released: int, max: ?float, status: string}>
     */
    private static function cells(Collection $byDate, array $dates): array
    {
        return array_map(function (string $date) use ($byDate): array {
            $day = $byDate->get($date);
            $runs = $day['runs'] ?? 0;
            $failed = $day['failed'] ?? 0;

            return [
                'date' => $date,
                'runs' => $runs,
                'failed' => $failed,
                'released' => $day['released'] ?? 0,
                'max' => $day['max'] ?? null,
                'status' => match (true) {
                    $failed > 0 => 'failed',
                    $runs > 0 => 'ok',
                    default => 'idle',
                },
            ];
        }, $dates);
    }

    private static function hourly(CarbonImmutable $date, RunSql $sql): Builder
    {
        return JobRun::query()
            ->where('started_at', '>=', $date->startOfDay())
            ->where('started_at', '<', $date->startOfDay()->addDay())
            ->toBase()
            ->selectRaw(implode(', ', [
                $sql->slot('started_at', $date->startOfDay(), 3600).' as hour',
                'count(*) as runs',
                RunSql::countWhere(RunSql::FAILED).' as failed',
                RunSql::countWhere("status = 'released'").' as released',
                RunSql::maxWhere('duration_ms', RunSql::TIMED).' as max_ms',
                $sql->percentiles('wait', 'wait_ms', 'wait_ms is not null', [0.95]),
            ]))
            ->groupBy('hour');
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<array{runs: int, failed: int, released: int, max: ?float, wait_p95: ?float}>
     */
    private static function hourRow(Collection $rows, RunSql $sql): array
    {
        $byHour = $rows->keyBy(fn (object $row): int => (int) $row->hour);

        return array_map(function (int $hour) use ($byHour, $sql): array {
            $row = $byHour->get($hour);
            $wait = $sql->percentile($row, 'wait', 0.95);

            return [
                'runs' => (int) ($row->runs ?? 0),
                'failed' => (int) ($row->failed ?? 0),
                'released' => (int) ($row->released ?? 0),
                'max' => isset($row->max_ms) ? round((float) $row->max_ms / 1000, 1) : null,
                'wait_p95' => $wait === null ? null : round($wait / 1000, 1),
            ];
        }, range(0, 23));
    }
}
