<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Phattarachai\OpsCore\Alerts\ProbeLevel;
use Phattarachai\OpsLaravel\Enums\ScheduleRunStatus;
use Phattarachai\OpsLaravel\Models\Deploy;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Models\ProbeState;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Samplers\ScheduleHealthProbe;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\Reports\Schedules\ScheduleBoard;
use Phattarachai\OpsLaravel\Ui\TimeRange;

beforeEach(function (): void {
    Ops::auth(fn (Request $request): bool => true);
    $this->travelTo(CarbonImmutable::parse('2026-09-28 12:30:00'));
    app(Schedule::class)->call(fn () => null)->description('ProbeHourly')->hourly();
});

function schedulesUiRun(string $at, ScheduleRunStatus $status, string $task = 'ProbeHourly', ?int $durationMs = 1000, string $expression = '0 * * * *'): ScheduleRun
{
    return ScheduleRun::query()->create([
        'task' => $task,
        'expression' => $expression,
        'status' => $status,
        'started_at' => CarbonImmutable::parse($at),
        'duration_ms' => $durationMs,
    ]);
}

it('lists the live schedule with cadence, health and a windowed link', function (): void {
    app(Schedule::class)->command('inspire')->dailyAt('07:00');
    app(Schedule::class)->command('inspire')->dailyAt('18:00');
    schedulesUiRun('2026-09-28 12:00:01', ScheduleRunStatus::Success);

    $this->get(route('ops.schedules', ['window' => '7d']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'schedules')
            ->where('mode', 'list')
            ->where('time_filter.window', '7d')
            ->has('dates', 14)
            ->where('timeline', null)
            ->where('tasks', function ($tasks): bool {
                $probe = collect($tasks)->firstWhere('task', 'ProbeHourly');
                $inspire = collect($tasks)->firstWhere('task', 'inspire');

                return $probe['url'] === route('ops.schedules.show', ['token' => Format::base64url('ProbeHourly'), 'window' => '7d'])
                    && $probe['cadence']['kind'] === 'hourly'
                    && $probe['runs'] === 1
                    && count($probe['history']) === 14
                    && $inspire['expressions'] === ['0 7 * * *', '0 18 * * *']
                    && $inspire['cadence']['label'] === 'Daily 07:00, 18:00'
                    && $inspire['health'] === 'no_data'
                    && $inspire['missed'] === null;
            }));
});

it('counts a cron tick with no run and no skip as missed', function (): void {
    schedulesUiRun('2026-09-28 09:00:02', ScheduleRunStatus::Success);
    schedulesUiRun('2026-09-28 10:00:03', ScheduleRunStatus::Success);
    schedulesUiRun('2026-09-28 12:00:01', ScheduleRunStatus::Skipped, durationMs: null);

    $this->get(route('ops.schedules'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tasks', fn ($tasks): bool => collect(collect($tasks)->firstWhere('task', 'ProbeHourly'))->only(['missed', 'runs', 'skipped', 'health'])->all() === [
                'runs' => 2,
                'skipped' => 1,
                'missed' => 1,
                'health' => 'missed',
            ]));
});

it('counts each missing minute of an every-minute task and ignores the tick before the window', function (): void {
    app(Schedule::class)->call(fn () => null)->description('ProbeMinute')->everyMinute();
    $this->travelTo(CarbonImmutable::parse('2026-09-29 12:00:30'));

    foreach (['11:40', '11:41', '11:42', '11:46', '11:47', '11:48', '11:49', '11:50', '11:51', '11:52', '11:53', '11:54', '11:55'] as $minute) {
        schedulesUiRun("2026-09-29 {$minute}:02", ScheduleRunStatus::Success, task: 'ProbeMinute', expression: '* * * * *');
    }

    $this->get(route('ops.schedules'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tasks', fn ($tasks): bool => collect($tasks)->firstWhere('task', 'ProbeMinute')['missed'] === 3));
});

it('judges a changed schedule only from its first run on the new expression', function (): void {
    app(Schedule::class)->call(fn () => null)->description('ProbeBackup')->dailyAt('03:30');
    schedulesUiRun('2026-09-28 01:06:01', ScheduleRunStatus::Success, task: 'ProbeBackup', expression: '6 1 * * *');

    $missed = fn (): ?int => collect(ScheduleBoard::tasks(TimeRange::fromPreset('24h')))->firstWhere('task', 'ProbeBackup')['missed'];

    expect($missed())->toBeNull()
        ->and(collect(ScheduleBoard::tasks(TimeRange::fromPreset('24h')))->firstWhere('task', 'ProbeBackup')['health'])->toBe('ok');

    $this->travelTo(CarbonImmutable::parse('2026-09-30 12:30:00'));
    schedulesUiRun('2026-09-29 03:30:02', ScheduleRunStatus::Success, task: 'ProbeBackup', expression: '30 3 * * *');
    OpsCache::bust();

    expect($missed())->toBe(1);
});

it('excuses ticks within the grace period of a deploy, for the board and the probe alike', function (): void {
    app(Schedule::class)->call(fn () => null)->description('ProbeMinute')->everyMinute();
    $this->travelTo(CarbonImmutable::parse('2026-09-29 12:00:30'));

    foreach (['11:40', '11:41', '11:42', '11:43', '11:44', '11:46', '11:47', '11:48', '11:49', '11:50', '11:51', '11:52', '11:53', '11:54', '11:55'] as $minute) {
        schedulesUiRun("2026-09-29 {$minute}:02", ScheduleRunStatus::Success, task: 'ProbeMinute', expression: '* * * * *');
    }

    $row = fn (): array => collect(ScheduleBoard::tasks(TimeRange::fromPreset('24h')))->firstWhere('task', 'ProbeMinute');

    expect($row()['missed'])->toBe(1);

    Deploy::query()->create(['release' => 'abc123', 'deployed_at' => CarbonImmutable::parse('2026-09-29 11:49:40')]);
    OpsCache::bust();

    expect($row())->missed->toBe(0)->health->toBe('ok');

    app(ScheduleHealthProbe::class)->publish();

    expect(ProbeState::query()->where('key', 'ProbeMinute')->sole())->level->toBe(ProbeLevel::Ok);
});

it('reads failing from the last run and lists the jobs each run dispatched', function (): void {
    $run = schedulesUiRun('2026-09-28 12:00:01', ScheduleRunStatus::Failed);
    $run->update(['exception_class' => RuntimeException::class, 'exception_message' => 'boom']);
    JobRun::query()->create([
        'uuid' => (string) Str::uuid(), 'job_class' => 'App\\Jobs\\FooJob', 'queue' => 'default', 'connection' => 'redis',
        'attempts' => 1, 'status' => 'failed', 'started_at' => CarbonImmutable::now(), 'schedule_run_id' => $run->id,
    ]);

    $this->get(route('ops.schedules.show', ['token' => Format::base64url('ProbeHourly'), 'window' => '6h']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'schedules.show')
            ->where('index_url', route('ops.schedules', ['window' => '6h']))
            ->where('name', 'ProbeHourly')
            ->where('summary.health', 'failing')
            ->where('summary.failed', 1)
            ->has('runs', 1)
            ->where('runs.0.status', 'failed')
            ->where('runs.0.detail', 'boom')
            ->where('runs.0.jobs', 1)
            ->where('runs.0.jobs_failed', 1)
            ->where('runs.0.job_classes', ['FooJob'])
            ->has('incidents', 1)
            ->where('incidents.0.exception', 'RuntimeException')
            ->where('buckets', fn ($buckets): bool => collect($buckets)->sum('schedule') === 1 && collect($buckets)->sum('failed') === 1)
            ->has('history', 14)
            ->where('history.13.status', 'failed'));
});

it('answers 404 for a token of a task that is not scheduled', function (): void {
    $this->get(route('ops.schedules.show', ['token' => Format::base64url('GhostTask')]))->assertNotFound();
});

it('lays out a day with planned, actual and missed runs, and per-hour counts for frequent tasks', function (): void {
    app(Schedule::class)->call(fn () => null)->description('ProbeMinute')->everyMinute();
    schedulesUiRun('2026-09-28 08:00:04', ScheduleRunStatus::Success);
    schedulesUiRun('2026-09-28 10:00:02', ScheduleRunStatus::Failed);
    schedulesUiRun('2026-09-28 00:01:02', ScheduleRunStatus::Success, task: 'ProbeMinute');

    $this->get(route('ops.schedules', ['view' => 'timeline', 'day' => '2026-09-28']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('mode', 'timeline')
            ->where('timeline.date', '2026-09-28')
            ->where('timeline.tasks', function ($tasks): bool {
                $hourly = collect($tasks)->firstWhere('task', 'ProbeHourly');
                $minute = collect($tasks)->firstWhere('task', 'ProbeMinute');

                return count($hourly['planned']) === 13
                    && $hourly['missed'] === [0, 60, 120, 180, 240, 300, 360, 420, 540, 660, 720]
                    && count($hourly['runs']) === 2
                    && $hourly['hours'] === null
                    && $minute['hours'][0]['runs'] === 1
                    && $minute['planned'] === [];
            }));
});

it('opens any past day, clamps the future to today and marks nothing missed before recording began', function (): void {
    schedulesUiRun('2026-09-28 08:00:04', ScheduleRunStatus::Success);

    $this->get(route('ops.schedules', ['view' => 'timeline', 'day' => '2026-08-01']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('timeline.date', '2026-08-01')
            ->where('timeline.today', '2026-09-28')
            ->where('timeline.tasks', fn ($tasks): bool => collect($tasks)->every(fn ($task): bool => $task['missed'] === [])
                && count(collect($tasks)->firstWhere('task', 'ProbeHourly')['planned']) === 24));

    $this->get(route('ops.schedules', ['view' => 'timeline', 'day' => '2026-09-29']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('timeline.date', '2026-09-28'));
});

it('marks a matrix day missed only from the first recorded day on', function (): void {
    schedulesUiRun('2026-09-26 08:00:04', ScheduleRunStatus::Success);
    schedulesUiRun('2026-09-28 08:00:04', ScheduleRunStatus::Skipped, durationMs: null);

    $this->get(route('ops.schedules', ['view' => 'matrix']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('mode', 'matrix')
            ->where('tasks', fn ($tasks): bool => collect(collect($tasks)->firstWhere('task', 'ProbeHourly')['history'])->pluck('status', 'date')->only(['2026-09-25', '2026-09-26', '2026-09-27', '2026-09-28'])->all() === [
                '2026-09-25' => 'idle',
                '2026-09-26' => 'ok',
                '2026-09-27' => 'missed',
                '2026-09-28' => 'skipped',
            ]));
});

it('flags horizon-silenced tasks so the page can hide them', function (): void {
    config()->set('horizon.silenced', ['ProbeHourly']);

    $this->get(route('ops.schedules'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tasks', fn ($tasks): bool => collect($tasks)->firstWhere('task', 'ProbeHourly')['silenced'] === true
                && collect($tasks)->where('task', '!=', 'ProbeHourly')->every(fn ($task): bool => $task['silenced'] === false)));
});
