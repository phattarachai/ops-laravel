<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Phattarachai\OpsLaravel\Enums\ScheduleRunStatus;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Support\OpsContext;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

uses(WithConsoleEvents::class);

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

it('records a skip for each due task the scheduler drops in maintenance mode', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 20:25:00'));
    app(Schedule::class)->command('inspire')->everyFiveMinutes();
    app(Schedule::class)->command('inspire --verbose')->hourly();
    app(Schedule::class)->call(fn () => null)->description('ProbeUp')->everyFiveMinutes()->evenInMaintenanceMode();
    app()->maintenanceMode()->activate([]);

    try {
        $this->artisan('schedule:run')->assertSuccessful();
    } finally {
        app()->maintenanceMode()->deactivate();
    }

    expect(ScheduleRun::query()->whereIn('task', ['ProbeUp', 'inspire', 'inspire --verbose'])->orderBy('task')->get()->map->only(['task', 'expression', 'status'])->all())->toBe([
        ['task' => 'ProbeUp', 'expression' => '*/5 * * * *', 'status' => ScheduleRunStatus::Success],
        ['task' => 'inspire', 'expression' => '*/5 * * * *', 'status' => ScheduleRunStatus::Skipped],
    ]);
});

it('records no maintenance skips while the app is up', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 20:25:00'));
    app(Schedule::class)->command('inspire')->everyFiveMinutes();

    event(new CommandStarting('schedule:run', new ArrayInput([]), new NullOutput));

    expect(ScheduleRun::query()->exists())->toBeFalse();
});
