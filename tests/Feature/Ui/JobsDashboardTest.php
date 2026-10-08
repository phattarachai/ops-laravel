<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\JobTimeline;

const JOBS_UI_FOO = 'App\\Jobs\\FooJob';

const JOBS_UI_CART = 'App\\Jobs\\Cart\\SyncCart';

beforeEach(function (): void {
    Ops::auth(fn (Request $request): bool => true);
    $this->travelTo(CarbonImmutable::parse('2026-09-28 12:30:00'));
});

/** @param  array<string, mixed>  $attributes */
function jobsUiRun(string $jobClass = JOBS_UI_FOO, string $status = 'success', ?CarbonImmutable $at = null, array $attributes = []): JobRun
{
    $at ??= CarbonImmutable::now()->subHour();

    return JobRun::query()->create([
        'uuid' => (string) Str::uuid(),
        'job_class' => $jobClass,
        'queue' => 'default',
        'connection' => 'redis',
        'attempts' => 1,
        'status' => $status,
        'started_at' => $at,
        'finished_at' => $at->addSecond(),
        'duration_ms' => 1000,
        'wait_ms' => 250,
        'exception_class' => in_array($status, ['failed', 'timed_out'], true) ? RuntimeException::class : null,
        'exception_message' => in_array($status, ['failed', 'timed_out'], true) ? 'boom 42' : null,
        ...$attributes,
    ]);
}

it('lists each job class worst first with its history, groups, queues and a windowed link', function (): void {
    config()->set('horizon.silenced', [JOBS_UI_CART]);
    jobsUiRun();
    jobsUiRun(status: 'failed');
    jobsUiRun(JOBS_UI_CART, attributes: ['queue' => 'cart']);
    jobsUiRun(JOBS_UI_CART, attributes: ['queue' => 'cart']);
    jobsUiRun(JOBS_UI_CART, attributes: ['queue' => null]);

    $this->get(route('ops.jobs', ['window' => '7d']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'jobs')
            ->where('mode', 'list')
            ->where('time_filter.window', '7d')
            ->has('dates', 14)
            ->where('matrix', null)
            ->where('jobs.0.job_class', JOBS_UI_FOO)
            ->where('jobs.0', fn ($foo): bool => $foo['url'] === route('ops.jobs.show', ['token' => Format::base64url(JOBS_UI_FOO), 'window' => '7d'])
                && $foo['runs'] === 2
                && $foo['failed'] === 1
                && $foo['fail_pct'] === 50
                && $foo['health'] === 'incident'
                && $foo['group'] === 'System'
                && $foo['silenced'] === false
                && count($foo['history']) === 14
                && collect($foo['history'])->last()['status'] === 'failed'
                && $foo['spark_outcome'] === [['ok' => 1, 'timeout' => 1]])
            ->where('jobs.1', fn ($cart): bool => $cart['group'] === 'Cart'
                && $cart['silenced'] === true
                && $cart['queue'] === 'cart'
                && $cart['queues'] === ['cart', 'default']
                && $cart['more_queues'] === 1
                && $cart['health'] === 'ok'));
});

it('groups by the configured namespace prefixes first', function (): void {
    config()->set('ops.ui.job_groups', ['App\\Jobs\\Cart\\' => 'Checkout']);
    config()->set('ops.ui.collapsed_job_groups', ['Checkout']);
    jobsUiRun(JOBS_UI_CART);
    jobsUiRun('Vendor\\Package\\Thing');

    $this->get(route('ops.jobs'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('collapsed_groups', ['Checkout'])
            ->where('jobs', fn ($jobs): bool => collect($jobs)->pluck('group', 'job')->all() === ['SyncCart' => 'Checkout', 'Thing' => 'Other']));
});

it('counts timed out runs as failed, released attempts apart and keeps them out of durations', function (): void {
    jobsUiRun(attributes: ['duration_ms' => 1000]);
    jobsUiRun(status: 'released', attributes: ['duration_ms' => 180000]);
    jobsUiRun(status: 'timed_out', attributes: ['duration_ms' => 2000]);

    $this->get(route('ops.jobs'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('jobs.0.runs', 3)
            ->where('jobs.0.failed', 1)
            ->where('jobs.0.released', 1)
            ->where('jobs.0.avg_ms', 1500)
            ->where('jobs.0.p95_ms', fn (int $p95): bool => $p95 >= 1000 && $p95 <= 2000));
});

it('carries every class seen in fourteen days in the matrix, even outside the window', function (): void {
    jobsUiRun(at: CarbonImmutable::now()->subDays(5));

    $this->get(route('ops.jobs', ['view' => 'matrix']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('mode', 'matrix')
            ->where('jobs', [])
            ->where('matrix.0.job_class', JOBS_UI_FOO)
            ->where('matrix.0.runs', 1)
            ->where('matrix.0.history.8.status', 'ok')
            ->where('matrix.0.history.8.date', '2026-09-23'));
});

it('returns one day by hour and clamps a future or malformed day to today', function (): void {
    jobsUiRun(status: 'failed', at: CarbonImmutable::parse('2026-09-25 04:10:00'), attributes: ['wait_ms' => 3000]);

    $this->get(route('ops.jobs', ['view' => 'timeline', 'day' => '2026-09-25']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('timeline.date', '2026-09-25')
            ->where('timeline.today', '2026-09-28')
            ->where('timeline.hours.4.runs', 1)
            ->where('timeline.hours.4.wait_p95', 3)
            ->where('timeline.jobs.0.hours.4.failed', 1)
            ->where('timeline.jobs.0.failed', 1));

    foreach (['2026-09-29', 'not-a-date', '2026-02-31'] as $day) {
        $this->get(route('ops.jobs', ['view' => 'timeline', 'day' => $day]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('timeline.date', '2026-09-28'));
    }
});

it('maps queues onto horizon supervisors and lists queues no supervisor drains', function (): void {
    config()->set('horizon.defaults', ['app-supervisor' => ['connection' => 'redis', 'queue' => ['high', 'default'], 'maxProcesses' => 3, 'timeout' => 90]]);
    jobsUiRun(attributes: ['queue' => 'default']);
    jobsUiRun(attributes: ['queue' => 'nobody-listens']);

    $this->get(route('ops.jobs', ['view' => 'workers']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('horizon', true)
            ->where('workers.0.name', 'app-supervisor')
            ->where('workers.0.queues.0.queue', 'high')
            ->where('workers.0.queues.0.runs', 0)
            ->where('workers.0.queues.1.queue', 'default')
            ->where('workers.0.queues.1.priority', 2)
            ->where('workers.0.queues.1.runs', 1)
            ->where('workers.1.name', null)
            ->where('workers.1.queues.0.queue', 'nobody-listens'));
});

it('lists every queue without a supervisor when horizon is absent', function (): void {
    jobsUiRun();

    $this->get(route('ops.jobs', ['view' => 'workers']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('horizon', false)
            ->has('workers', 1)
            ->where('workers.0.name', null)
            ->where('workers.0.queues.0.queue', 'default'));
});

it('shows a job class with origins, sweeps, charts, recent runs and incidents', function (): void {
    config()->set('horizon.defaults', ['app-supervisor' => ['queue' => ['default'], 'timeout' => 120]]);
    $sweep = ScheduleRun::query()->create(['task' => 'App\\Jobs\\IterateFoo', 'expression' => '0 11 * * *', 'status' => 'success', 'started_at' => CarbonImmutable::parse('2026-09-28 11:00:00')]);
    $at = CarbonImmutable::parse('2026-09-28 11:00:00');
    jobsUiRun(at: $at, attributes: ['origin' => 'schedule', 'schedule_run_id' => $sweep->id, 'duration_ms' => 10000, 'finished_at' => $at->addSeconds(10)]);
    jobsUiRun(status: 'failed', at: $at->addMinute(), attributes: ['origin' => 'schedule', 'schedule_run_id' => $sweep->id, 'finished_at' => $at->addMinutes(6)]);
    jobsUiRun(at: $at, attributes: ['origin' => 'web', 'queue' => 'web-queue', 'wait_ms' => 4000]);
    jobsUiRun(status: 'released', at: $at, attributes: ['duration_ms' => 180000]);
    jobsUiRun(status: 'failed', at: $at->addMinutes(2), attributes: ['domain_type' => 'App\\Models\\Company', 'domain_id' => '0010']);

    $this->get(route('ops.jobs.show', ['token' => Format::base64url(JOBS_UI_FOO), 'window' => '6h']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'jobs.show')
            ->where('index_url', route('ops.jobs', ['window' => '6h']))
            ->where('job.job', 'FooJob')
            ->where('summary.runs', 5)
            ->where('summary.failed', 2)
            ->where('ceiling', 120)
            ->where('queues', ['default', 'web-queue'])
            ->where('origins', fn ($origins): bool => collect($origins)->pluck('runs', 'key')->all() === ['schedule' => 2, 'web' => 1, 'other' => 2]
                && collect($origins)->firstWhere('key', 'web')['wait_p95'] === '4s'
                && collect($origins)->firstWhere('key', 'other')['released'] === 1)
            ->has('sweeps', 1)
            ->where('sweeps.0.runs', 2)
            ->where('sweeps.0.failed', 1)
            ->where('sweeps.0.task', 'IterateFoo')
            ->where('sweeps.0.minutes', 6)
            ->where('buckets', fn ($buckets): bool => collect($buckets)->sum('schedule') === 2 && collect($buckets)->sum('web') === 1 && collect($buckets)->sum('released') === 1 && collect($buckets)->max('max') == 10)
            ->where('hours.11', ['runs' => 5, 'failed' => 2])
            ->where('histogram', fn ($bands): bool => count($bands) === 13 && collect($bands)->sum('count') === 4)
            ->has('runs', 5)
            ->where('runs.0.domain', 'Company #0010')
            ->has('incidents', 1)
            ->where('incidents.0.count', 2)
            ->where('incidents.0.domains', ['0010'])
            ->has('history', 14));
});

it('answers 404 for a token of a class never recorded', function (): void {
    jobsUiRun();

    $this->get(route('ops.jobs.show', ['token' => Format::base64url('App\\Jobs\\GhostJob')]))->assertNotFound();
    $this->get(route('ops.jobs.show', ['token' => '!!!']))->assertNotFound();
});

it('recomputes today on refresh while the closed days of the history stay cached', function (): void {
    jobsUiRun(at: CarbonImmutable::now()->subDay());
    JobTimeline::history();

    jobsUiRun(status: 'failed', at: CarbonImmutable::now()->subDay());
    jobsUiRun(at: CarbonImmutable::now()->subHour());
    OpsCache::bust();

    $days = collect(JobTimeline::history()[JOBS_UI_FOO])->keyBy('date');

    expect($days['2026-09-27'])->toMatchArray(['runs' => 1, 'failed' => 0])
        ->and($days['2026-09-28'])->toMatchArray(['runs' => 1, 'status' => 'ok']);
});
