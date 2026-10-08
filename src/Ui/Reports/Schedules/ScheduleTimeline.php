<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Schedules;

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\Days;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\RunSql;
use stdClass;

/**
 * The 14-day task × day matrix and one day's Gantt: planned ticks, actual runs and missed ticks
 * for the non-frequent tasks, per-hour counts for the frequent ones. A day or tick reads as missed
 * only once its grace has passed and only from the task's first recorded day.
 */
final class ScheduleTimeline
{
    /**
     * @return array<string, list<array{date: string, runs: int, failed: int, skipped: int, max: ?float, status: string}>>
     */
    public static function history(int $days = Days::HISTORY): array
    {
        $today = CarbonImmutable::today();

        return OpsCache::remember("schedules:history:{$days}:{$today->toDateString()}", function () use ($days, $today): array {
            $closed = OpsCache::rememberClosedDays("schedules:history:{$days}", fn (): array => self::dailyRows($today->subDays($days - 1), $today));
            $rows = collect([...$closed, ...self::dailyRows($today, $today->addDay())])
                ->groupBy('task')
                ->map(fn (Collection $taskDays): Collection => $taskDays->keyBy('date'));
            $firstSeen = ScheduleBoard::firstSeen();
            $dates = array_column(Days::history($days), 'date');

            return ScheduledTasks::events()
                ->map(fn (Collection $events, string $task): array => array_map(
                    fn (string $date): array => self::cell($rows->get($task)?->get($date), $events, CarbonImmutable::parse($date), $firstSeen->get($task)),
                    $dates,
                ))
                ->all();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $tasks  ScheduleBoard rows carrying `url`
     * @return list<array<string, mixed>>
     */
    public static function day(CarbonImmutable $date, array $tasks): array
    {
        $timeline = OpsCache::remember("schedules:day:{$date->toDateString()}", function () use ($date, $tasks): array {
            $frequent = collect($tasks)->filter(fn (array $task): bool => $task['cadence']['kind'] === 'frequent')->pluck('task')->all();
            $runs = self::runsOn($date, $frequent);
            $hours = self::hoursOn($date, $frequent);
            $events = ScheduledTasks::events();
            $firstSeen = ScheduleBoard::firstSeen();

            return collect($tasks)->mapWithKeys(fn (array $task): array => [$task['task'] => self::dayRow(
                $task,
                $date,
                $runs->get($task['task'], collect()),
                $hours->get($task['task'], collect()),
                $events->get($task['task'], collect()),
                $firstSeen->get($task['task']),
            )])->all();
        });

        return array_map(fn (array $task): array => ['name' => $task['name'], 'url' => $task['url'] ?? null, 'cadence' => $task['cadence'], ...($timeline[$task['task']] ?? self::emptyRow($task['task']))], $tasks);
    }

    /**
     * @param  array<string, mixed>  $task
     * @param  Collection<int, ScheduleRun>  $runs
     * @param  Collection<int, stdClass>  $hours
     * @param  Collection<int, Event>  $events
     * @return array{task: string, runs: list<array<string, mixed>>, hours: ?list<array{runs: int, failed: int, skipped: int}>, planned: list<int>, missed: list<int>}
     */
    private static function dayRow(array $task, CarbonImmutable $date, Collection $runs, Collection $hours, Collection $events, ?CarbonImmutable $firstSeen): array
    {
        $isFrequent = $task['cadence']['kind'] === 'frequent';
        $planned = $isFrequent ? [] : self::plannedOn($events, $date);

        return [
            'task' => (string) $task['task'],
            'runs' => $runs->map(fn (ScheduleRun $run): array => [
                'at' => $run->started_at->format('H:i:s'),
                'minute' => round($run->started_at->hour * 60 + $run->started_at->minute + $run->started_at->second / 60, 2),
                'seconds' => $run->duration_ms === null ? null : round($run->duration_ms / 1000, 1),
                'duration' => Format::duration($run->duration_ms === null ? null : $run->duration_ms / 1000),
                'status' => $run->status->value,
            ])->values()->all(),
            'hours' => $isFrequent ? self::hourRow($hours) : null,
            'planned' => $planned,
            'missed' => self::recordedOn($firstSeen, $date) ? self::missedSlots($planned, $runs) : [],
        ];
    }

    /**
     * @return array{task: string, runs: list<array<string, mixed>>, hours: null, planned: list<int>, missed: list<int>}
     */
    private static function emptyRow(string $task): array
    {
        return ['task' => $task, 'runs' => [], 'hours' => null, 'planned' => [], 'missed' => []];
    }

    /**
     * @return list<array{task: string, date: string, runs: int, failed: int, skipped: int, max: ?float}>
     */
    private static function dailyRows(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return ScheduleRun::query()
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $until)
            ->toBase()
            ->selectRaw(implode(', ', [
                'task',
                RunSql::make()->slot('started_at', $from, 86400).' as day_index',
                RunSql::countWhere(RunSql::RAN).' as runs',
                RunSql::countWhere("status = 'failed'").' as failed',
                RunSql::countWhere("status = 'skipped'").' as skipped',
                RunSql::maxWhere('duration_ms', RunSql::RAN).' as max_ms',
            ]))
            ->groupBy('task', 'day_index')
            ->get()
            ->map(fn (object $row): array => [
                'task' => (string) $row->task,
                'date' => $from->addDays((int) $row->day_index)->toDateString(),
                'runs' => (int) $row->runs,
                'failed' => (int) $row->failed,
                'skipped' => (int) $row->skipped,
                'max' => $row->max_ms === null ? null : round((float) $row->max_ms / 1000, 1),
            ])
            ->all();
    }

    /**
     * @param  array{runs: int, failed: int, skipped: int, max: ?float}|null  $row
     * @param  Collection<int, Event>  $events
     * @return array{date: string, runs: int, failed: int, skipped: int, max: ?float, status: string}
     */
    private static function cell(?array $row, Collection $events, CarbonImmutable $date, ?CarbonImmutable $firstSeen): array
    {
        $runs = $row['runs'] ?? 0;
        $failed = $row['failed'] ?? 0;
        $skipped = $row['skipped'] ?? 0;

        return [
            'date' => $date->toDateString(),
            'runs' => $runs,
            'failed' => $failed,
            'skipped' => $skipped,
            'max' => $row['max'] ?? null,
            'status' => match (true) {
                $failed > 0 => 'failed',
                $runs > 0 => 'ok',
                $skipped > 0 => 'skipped',
                self::recordedOn($firstSeen, $date) && self::plannedOn($events, $date, limit: 1) !== [] => 'missed',
                default => 'idle',
            },
        ];
    }

    /**
     * Minutes of the day a tick is due, only ticks already past the grace window.
     *
     * @param  Collection<int, Event>  $events
     * @return list<int>
     */
    private static function plannedOn(Collection $events, CarbonImmutable $date, int $limit = 48): array
    {
        $end = $date->endOfDay()->min(CarbonImmutable::now()->subMinutes(ScheduledTasks::GRACE_MINUTES));

        if ($end->lessThan($date->startOfDay())) {
            return [];
        }

        $minutes = array_map(function (int $timestamp): int {
            $tick = CarbonImmutable::createFromTimestamp($timestamp, (string) config('app.timezone'));

            return $tick->hour * 60 + $tick->minute;
        }, ScheduledTasks::ticks($events, $date->startOfDay(), $end->addSecond(), $limit));

        return array_values(array_unique($minutes));
    }

    private static function recordedOn(?CarbonImmutable $firstSeen, CarbonImmutable $date): bool
    {
        return $firstSeen instanceof CarbonImmutable && $date->greaterThanOrEqualTo($firstSeen->startOfDay());
    }

    /**
     * @param  list<int>  $planned
     * @param  Collection<int, ScheduleRun>  $runs
     * @return list<int>
     */
    private static function missedSlots(array $planned, Collection $runs): array
    {
        $started = $runs->map(fn (ScheduleRun $run): float => $run->started_at->hour * 60 + $run->started_at->minute + $run->started_at->second / 60);

        return array_values(array_filter(
            $planned,
            fn (int $tick): bool => $started->doesntContain(fn (float $minute): bool => $minute >= $tick && $minute < $tick + ScheduledTasks::GRACE_MINUTES),
        ));
    }

    /**
     * @param  list<string>  $frequent
     * @return Collection<array-key, EloquentCollection<int, ScheduleRun>>
     */
    private static function runsOn(CarbonImmutable $date, array $frequent): Collection
    {
        return ScheduleRun::query()
            ->where('started_at', '>=', $date->startOfDay())
            ->where('started_at', '<', $date->startOfDay()->addDay())
            ->whereNotIn('task', $frequent)
            ->oldest('started_at')
            ->limit(5000)
            ->get(['task', 'status', 'started_at', 'duration_ms'])
            ->groupBy('task');
    }

    /**
     * @param  list<string>  $frequent
     * @return Collection<array-key, Collection<int, stdClass>>
     */
    private static function hoursOn(CarbonImmutable $date, array $frequent): Collection
    {
        return ScheduleRun::query()
            ->where('started_at', '>=', $date->startOfDay())
            ->where('started_at', '<', $date->startOfDay()->addDay())
            ->whereIn('task', $frequent)
            ->toBase()
            ->selectRaw(implode(', ', [
                'task',
                RunSql::make()->slot('started_at', $date->startOfDay(), 3600).' as hour',
                RunSql::countWhere(RunSql::RAN).' as runs',
                RunSql::countWhere("status = 'failed'").' as failed',
                RunSql::countWhere("status = 'skipped'").' as skipped',
            ]))
            ->groupBy('task', 'hour')
            ->get()
            ->groupBy('task');
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<array{runs: int, failed: int, skipped: int}>
     */
    private static function hourRow(Collection $rows): array
    {
        $byHour = $rows->keyBy(fn (object $row): int => (int) $row->hour);

        return array_map(fn (int $hour): array => [
            'runs' => (int) ($byHour->get($hour)->runs ?? 0),
            'failed' => (int) ($byHour->get($hour)->failed ?? 0),
            'skipped' => (int) ($byHour->get($hour)->skipped ?? 0),
        ], range(0, 23));
    }
}
