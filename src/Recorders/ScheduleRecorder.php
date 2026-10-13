<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Recorders;

use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Phattarachai\OpsLaravel\Enums\ScheduleRunStatus;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use Phattarachai\OpsLaravel\Support\OpsContext;
use Throwable;

/**
 * One ops_schedule_runs row per scheduled run, plus `skipped` rows when a filter said no or the
 * app was in maintenance mode. A `->command()` task's child process adopts the parent's running
 * row on CommandStarting.
 */
class ScheduleRecorder
{
    /** @var array<string, ScheduleRun> */
    protected array $running = [];

    public function __construct(protected OpsSettings $settings) {}

    public static function taskName(Event $event): string
    {
        return is_string($event->command)
            ? Str::of($event->command)->after("'artisan' ")->trim()->toString()
            : $event->getSummaryForDisplay();
    }

    public function starting(ScheduledTaskStarting $event): void
    {
        $this->guard(function () use ($event): void {
            $run = ScheduleRun::query()->create([
                'task' => self::taskName($event->task),
                'expression' => $event->task->getExpression(),
                'status' => ScheduleRunStatus::Running,
                'started_at' => now(),
            ]);

            $this->running[$event->task->mutexName()] = $run;
            OpsContext::stampOrigin(OpsContext::SCHEDULE, $run->id);
        });
    }

    public function finished(ScheduledTaskFinished $event): void
    {
        $status = ($event->task->exitCode ?? 0) === 0 ? ScheduleRunStatus::Success : ScheduleRunStatus::Failed;

        $this->close($event->task, $status, ['exit_code' => $event->task->exitCode]);
    }

    public function failed(ScheduledTaskFailed $event): void
    {
        $this->close($event->task, ScheduleRunStatus::Failed, [
            'exception_class' => $event->exception::class,
            'exception_message' => Str::limit($event->exception->getMessage(), 1000),
        ]);
    }

    public function skipped(ScheduledTaskSkipped $event): void
    {
        $this->guard(fn () => ScheduleRun::query()->create([
            'task' => self::taskName($event->task),
            'expression' => $event->task->getExpression(),
            'status' => ScheduleRunStatus::Skipped,
            'started_at' => now(),
        ]));
    }

    public function commandStarting(CommandStarting $event): void
    {
        if ($event->command === 'schedule:run') {
            $this->recordMaintenanceSkips();
        }

        $this->guard(function () use ($event): void {
            $run = $this->adoptableRun((string) $event->command);

            OpsContext::stampOrigin($run instanceof ScheduleRun ? OpsContext::SCHEDULE : OpsContext::CONSOLE, $run?->id);
        });
    }

    /**
     * While the app is down, `schedule:run` drops every task not marked `evenInMaintenanceMode()`
     * without firing any event, so its due tick would read as missed. Record it as skipped.
     */
    protected function recordMaintenanceSkips(): void
    {
        $this->guard(function (): void {
            if (! app()->isDownForMaintenance()) {
                return;
            }

            $now = CarbonImmutable::now();

            collect(app(Schedule::class)->events())
                ->filter(fn (Event $task): bool => ! $task->runsInMaintenanceMode() && $task->runsInEnvironment(app()->environment()) && self::isDue($task, $now))
                ->each(fn (Event $task) => ScheduleRun::query()->create([
                    'task' => self::taskName($task),
                    'expression' => $task->getExpression(),
                    'status' => ScheduleRunStatus::Skipped,
                    'started_at' => $now,
                ]));
        });
    }

    /** Mirrors Event::expressionPasses(), which is protected. */
    protected static function isDue(Event $task, CarbonImmutable $now): bool
    {
        $date = $task->timezone ? $now->setTimezone($task->timezone) : $now;

        return (new CronExpression($task->getExpression()))->isDue($date->toDateTimeString());
    }

    protected function adoptableRun(string $command): ?ScheduleRun
    {
        return ScheduleRun::query()
            ->where('status', ScheduleRunStatus::Running)
            ->where('started_at', '>=', now()->subMinutes(2))
            ->where(fn (Builder $query) => $query
                ->where('task', $command)
                ->orWhere('task', 'like', addcslashes($command, '%_\\').' %'))
            ->latest('started_at')
            ->first();
    }

    /** @param  array{exit_code?: int|null, exception_class?: string, exception_message?: string}  $attributes */
    protected function close(Event $task, ScheduleRunStatus $status, array $attributes): void
    {
        $this->guard(function () use ($task, $status, $attributes): void {
            $run = $this->running[$task->mutexName()] ?? null;

            if (! $run instanceof ScheduleRun) {
                return;
            }

            unset($this->running[$task->mutexName()]);
            OpsContext::stampOrigin(OpsContext::CONSOLE);

            $finishedAt = now();

            $run->update([
                ...$attributes,
                'status' => $status,
                'finished_at' => $finishedAt,
                'duration_ms' => (int) $run->started_at->diffInMilliseconds($finishedAt),
            ]);
        });
    }

    protected function guard(callable $callback): void
    {
        try {
            if (Ops::recording() && $this->settings->bool('recorders.schedule.enabled')) {
                $callback();
            }
        } catch (Throwable) {
        }
    }
}
