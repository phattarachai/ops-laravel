<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Phattarachai\OpsLaravel\Enums\ScheduleRunStatus;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Support\OpsContext;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

it('records a scheduled run from start to finish and stamps the schedule origin', function (): void {
    $task = app(Schedule::class)->command('inspire')->hourly();

    event(new ScheduledTaskStarting($task));

    expect(OpsContext::origin())->toBe('schedule')
        ->and(OpsContext::scheduleRunId())->toBe(ScheduleRun::query()->sole()->id);

    $task->exitCode = 1;
    event(new ScheduledTaskFinished($task, 0.2));

    expect(ScheduleRun::query()->sole())
        ->task->toBe('inspire')
        ->expression->toBe('0 * * * *')
        ->status->toBe(ScheduleRunStatus::Failed)
        ->exit_code->toBe(1);
});

it('records a skipped run', function (): void {
    $task = app(Schedule::class)->command('inspire')->daily();

    event(new ScheduledTaskSkipped($task));

    expect(ScheduleRun::query()->sole()->status)->toBe(ScheduleRunStatus::Skipped);
});

it('lets the child process of a command task adopt the running row', function (): void {
    $task = app(Schedule::class)->command('inspire --quiet')->everyFiveMinutes();
    event(new ScheduledTaskStarting($task));
    OpsContext::stampOrigin('console');

    event(new CommandStarting('inspire', new ArrayInput([]), new NullOutput));

    expect(OpsContext::origin())->toBe('schedule');
});
