<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Schedules;

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Phattarachai\OpsCore\Schedule\MissedTicks;
use Phattarachai\OpsLaravel\Enums\ScheduleRunStatus;
use Phattarachai\OpsLaravel\Models\Deploy;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\JobIdentity;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\RunSql;
use Phattarachai\OpsLaravel\Ui\TimeRange;

/**
 * One row per scheduled task: the live schedule merged with `ops_schedule_runs` for runs /
 * failed / skipped / p95 in the window, the last non-skipped run and the missed ticks of the last
 * 24 h (a tick with no run or skip starting within five minutes). Each cron expression's ticks
 * count only after the first recorded row with that expression, so a changed schedule never
 * judges the old one; the tick at that opening minute is left out. Ticks within the grace period
 * of an `ops_deploys` row are excused: cron can fire mid-cutover with nothing to record it.
 */
final class ScheduleBoard
{
    /** @var list<string> */
    private const array HEALTH_ORDER = ['failing', 'missed', 'ok', 'no_data'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function tasks(TimeRange $range): array
    {
        return OpsCache::remember("schedules:board:{$range->key}", fn (): array => self::freshTasks($range));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function task(string $task, TimeRange $range): ?array
    {
        return collect(self::tasks($range))->firstWhere('task', $task);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @param  list<int>  $seen  sorted run / skip start timestamps
     * @param  array<string, CarbonImmutable>  $recordingSince  the task's first recorded row per expression
     * @param  list<array{int, int}>  $excused  deploy windows, see deployWindows()
     */
    public static function missed(Collection $events, array $seen, array $recordingSince, array $excused = [], ?CarbonImmutable $now = null): ?int
    {
        $tracked = $events->filter(fn (Event $event): bool => isset($recordingSince[$event->getExpression()]));

        if ($tracked->isEmpty()) {
            return null;
        }

        $now ??= CarbonImmutable::now();
        $to = $now->subMinutes(ScheduledTasks::GRACE_MINUTES);
        $ticks = $tracked
            ->groupBy(fn (Event $event): string => $event->getExpression())
            ->flatMap(fn (Collection $group, string $expression): array => ScheduledTasks::ticks($group, self::countFrom($recordingSince[$expression], $now), $to))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return MissedTicks::count($ticks, $seen, ScheduledTasks::GRACE_MINUTES * 60, $excused);
    }

    /**
     * @return Collection<string, CarbonImmutable>
     */
    public static function firstSeen(): Collection
    {
        return ScheduleRun::query()
            ->toBase()
            ->selectRaw('task, min(started_at) as first_at')
            ->groupBy('task')
            ->pluck('first_at', 'task')
            ->map(fn (mixed $at): CarbonImmutable => CarbonImmutable::parse((string) $at));
    }

    /**
     * @return Collection<array-key, array<string, CarbonImmutable>>
     */
    public static function firstSeenByExpression(): Collection
    {
        return ScheduleRun::query()
            ->toBase()
            ->selectRaw('task, expression, min(started_at) as first_at')
            ->groupBy('task', 'expression')
            ->get()
            ->groupBy('task')
            ->map(fn (Collection $rows): array => $rows
                ->mapWithKeys(fn (object $row): array => [(string) $row->expression => CarbonImmutable::parse((string) $row->first_at)])
                ->all());
    }

    /**
     * Each deploy of the last 24 h as an excused [from, to] window, padded by the grace period on
     * both sides: `deployed_at` is when Ops noticed the release (the first `ops:rollup` on it, or
     * `ops:deploy`), so the cutover tick falls just before it.
     *
     * @return list<array{int, int}>
     */
    public static function deployWindows(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $pad = ScheduledTasks::GRACE_MINUTES * 60;

        return Deploy::query()
            ->where('deployed_at', '>=', $now->subDay()->subSeconds($pad))
            ->oldest('deployed_at')
            ->get(['deployed_at'])
            ->map(fn (Deploy $deploy): array => [$deploy->deployed_at->getTimestamp() - $pad, $deploy->deployed_at->getTimestamp() + $pad])
            ->values()
            ->all();
    }

    private static function countFrom(CarbonImmutable $recordingSince, CarbonImmutable $now): CarbonImmutable
    {
        return $recordingSince->max($now->subDay())->startOfMinute()->addMinute();
    }

    /** @return list<array<string, mixed>> */
    private static function freshTasks(TimeRange $range): array
    {
        $stats = self::stats($range);
        $last = self::lastRuns();
        $firstSeen = self::firstSeenByExpression();
        $seen = self::seenSinceYesterday();
        $deploys = self::deployWindows();

        return ScheduledTasks::events()
            ->map(fn (Collection $events, string $task): array => self::row(
                $task,
                $events,
                $stats->get($task),
                $last->get($task),
                self::missed($events, $seen->get($task, []), $firstSeen->get($task, []), $deploys),
            ))
            ->sortBy(fn (array $row): array => [array_search($row['health'], self::HEALTH_ORDER, true), $row['name']])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @param  array{runs: int, failed: int, skipped: int, p95: ?float}|null  $stats
     * @return array<string, mixed>
     */
    private static function row(string $task, Collection $events, ?array $stats, ?ScheduleRun $lastRun, ?int $missed): array
    {
        $failed = $stats['failed'] ?? 0;
        $expressions = $events->map(fn (Event $event): string => $event->getExpression())->unique()->values()->all();
        $nextDue = $events->map(fn (Event $event): CarbonImmutable => CarbonImmutable::instance($event->nextRunDate()))->min();
        $p95 = $stats['p95'] ?? null;

        return [
            'task' => $task,
            'name' => class_basename($task),
            'namespace' => str_contains($task, '\\') ? Str::beforeLast($task, '\\') : null,
            'token' => Format::base64url($task),
            'expressions' => $expressions,
            'cadence' => CronCadence::describe($expressions),
            'silenced' => JobIdentity::isSilenced($task),
            'next_due' => Format::date($nextDue),
            'next_due_at' => $nextDue?->toIso8601String(),
            'runs' => $stats['runs'] ?? 0,
            'failed' => $failed,
            'skipped' => $stats['skipped'] ?? 0,
            'missed' => $missed,
            'p95' => Format::duration($p95),
            'p95_seconds' => $p95 === null ? null : round($p95, 3),
            'last_run_at' => Format::date($lastRun?->started_at) ?? '—',
            'last_run_at_iso' => $lastRun?->started_at->toIso8601String(),
            'last_status' => $lastRun?->status->value,
            'last_duration' => Format::duration($lastRun?->duration_ms === null ? null : $lastRun->duration_ms / 1000),
            ...self::health(self::healthOf($lastRun, $failed, $missed)),
        ];
    }

    /**
     * @return array{health: string, health_label: string, health_color: string}
     */
    private static function health(string $health): array
    {
        return [
            'health' => $health,
            'health_label' => match ($health) {
                'failing' => 'Failing',
                'missed' => 'Missed runs',
                'no_data' => 'No data',
                default => 'Healthy',
            },
            'health_color' => match ($health) {
                'failing' => 'danger',
                'missed' => 'warning',
                'no_data' => 'gray',
                default => 'success',
            },
        ];
    }

    private static function healthOf(?ScheduleRun $lastRun, int $failed, ?int $missed): string
    {
        return match (true) {
            ! $lastRun instanceof ScheduleRun && $missed === null => 'no_data',
            $lastRun?->status === ScheduleRunStatus::Failed || $failed > 0 => 'failing',
            ($missed ?? 0) > 0 => 'missed',
            default => 'ok',
        };
    }

    /**
     * @return Collection<string, array{runs: int, failed: int, skipped: int, p95: ?float}>
     */
    private static function stats(TimeRange $range): Collection
    {
        $sql = RunSql::make();

        return ScheduleRun::query()
            ->where('started_at', '>=', $range->start)
            ->where('started_at', '<', $range->end)
            ->toBase()
            ->selectRaw(implode(', ', [
                'task',
                RunSql::countWhere(RunSql::RAN).' as runs',
                RunSql::countWhere("status = 'failed'").' as failed',
                RunSql::countWhere("status = 'skipped'").' as skipped',
                $sql->percentiles('duration', 'duration_ms', RunSql::RAN, [0.95]),
            ]))
            ->groupBy('task')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->task => [
                'runs' => (int) $row->runs,
                'failed' => (int) $row->failed,
                'skipped' => (int) $row->skipped,
                'p95' => self::seconds($sql->percentile($row, 'duration', 0.95)),
            ]]);
    }

    private static function seconds(?float $milliseconds): ?float
    {
        return $milliseconds === null ? null : $milliseconds / 1000;
    }

    /**
     * @return Collection<string, ScheduleRun>
     */
    private static function lastRuns(): Collection
    {
        return ScheduleRun::query()
            ->whereIn('id', ScheduleRun::query()->selectRaw('max(id)')->where('status', '<>', ScheduleRunStatus::Skipped->value)->groupBy('task'))
            ->get()
            ->keyBy('task');
    }

    /**
     * @return Collection<array-key, list<int>>
     */
    private static function seenSinceYesterday(): Collection
    {
        return ScheduleRun::query()
            ->where('started_at', '>=', CarbonImmutable::now()->subDay()->subMinutes(ScheduledTasks::GRACE_MINUTES))
            ->oldest('started_at')
            ->get(['task', 'started_at'])
            ->groupBy('task')
            ->map(fn (Collection $runs): array => array_values($runs->map(fn (ScheduleRun $run): int => $run->started_at->getTimestamp())->all()));
    }
}
