<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Schedules;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use DateTimeZone;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Phattarachai\OpsLaravel\Recorders\ScheduleRecorder;
use Phattarachai\OpsLaravel\Ui\Format;

/**
 * The live schedule (`app(Schedule::class)->events()` for this environment), grouped by the task
 * name the recorder writes, so one task scheduled by several cron calls is one row.
 */
final class ScheduledTasks
{
    public const int GRACE_MINUTES = 5;

    /**
     * @return Collection<string, Collection<int, Event>>
     */
    public static function events(): Collection
    {
        return collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => $event->runsInEnvironment(app()->environment()))
            ->groupBy(fn (Event $event): string => ScheduleRecorder::taskName($event));
    }

    public static function decode(string $token): ?string
    {
        $task = Format::fromBase64url($token);

        return $task !== null && self::events()->has($task) ? $task : null;
    }

    /**
     * Unix timestamps of every tick of the events in [$from, $to), sorted and unique.
     *
     * @param  Collection<int, Event>  $events
     * @return list<int>
     */
    public static function ticks(Collection $events, CarbonInterface $from, CarbonInterface $to, int $limit = 2000): array
    {
        $ticks = $events->flatMap(fn (Event $event): array => self::eventTicks($event, $from, $to, $limit))->unique()->sort()->values()->all();

        return array_slice($ticks, 0, $limit);
    }

    /**
     * @return list<int>
     */
    private static function eventTicks(Event $event, CarbonInterface $from, CarbonInterface $to, int $limit): array
    {
        $cron = new CronExpression($event->getExpression());
        $timezone = self::timezone($event);
        $ticks = [];
        $cursor = CarbonImmutable::instance($from);

        while (count($ticks) < $limit) {
            $next = CarbonImmutable::instance($cron->getNextRunDate($cursor, 0, true, $timezone));

            if ($next->greaterThanOrEqualTo($to)) {
                break;
            }

            $ticks[] = $next->getTimestamp();
            $cursor = $next->addMinute();
        }

        return $ticks;
    }

    private static function timezone(Event $event): string
    {
        $timezone = $event->timezone ?: config('app.timezone');

        return $timezone instanceof DateTimeZone ? $timezone->getName() : (string) $timezone;
    }
}
