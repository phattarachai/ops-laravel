<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Response;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\Days;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\JobBoard;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\JobDetail;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\JobIdentity;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\JobQueues;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\JobTimeline;
use Phattarachai\OpsLaravel\Ui\TimeFilter;
use Phattarachai\OpsLaravel\Ui\UiPage;

final class JobsController
{
    /** @var list<string> */
    private const array MODES = ['list', 'matrix', 'timeline', 'workers'];

    /** @var list<string> */
    private const array WINDOWED_MODES = ['list', 'workers'];

    public function index(Request $request): Response
    {
        $filter = TimeFilter::fromRequest($request);
        $mode = in_array($request->query('view'), self::MODES, true) ? (string) $request->query('view') : 'list';
        $history = JobTimeline::history();

        return UiPage::render('jobs', [
            'mode' => $mode,
            'time_filter' => $filter->toProps(JobBoard::dataUpdatedAt($filter->range())),
            'jobs' => in_array($mode, self::WINDOWED_MODES, true) ? $this->board($history, $filter) : [],
            'dates' => Days::history(),
            'collapsed_groups' => JobIdentity::collapsedGroups(),
            'matrix' => $mode === 'matrix' ? $this->matrix($history, $filter) : null,
            'timeline' => $mode === 'timeline' ? $this->timeline(Days::pick($request->query('day')), $filter) : null,
            'workers' => $mode === 'workers' ? JobQueues::board($filter->range()) : null,
            'horizon' => JobQueues::hasHorizon(),
        ]);
    }

    public function show(Request $request, string $token): Response
    {
        $jobClass = JobIdentity::decode($token) ?? abort(404);
        $filter = TimeFilter::fromRequest($request);
        $range = $filter->range();
        $summary = JobBoard::row($jobClass, $range);
        $origins = JobDetail::origins($jobClass, $range);
        $latest = JobRun::query()->where('job_class', $jobClass)->max('started_at');

        return UiPage::render('jobs.show', [
            'time_filter' => $filter->toProps($latest === null ? null : CarbonImmutable::parse((string) $latest)),
            'index_url' => route('ops.jobs', $filter->params()),
            'job' => JobIdentity::of($jobClass),
            'summary' => $summary,
            'queues' => collect($origins)->pluck('chips')->flatten()->unique()->sort()->values()->all(),
            'origins' => $origins,
            'sweeps' => JobDetail::sweeps($jobClass, $range),
            'buckets' => JobDetail::buckets($jobClass, $range),
            'ceiling' => $summary === null ? null : JobQueues::timeoutFor($summary['queue']),
            'hours' => JobDetail::hourOfDay($jobClass),
            'histogram' => JobDetail::histogram($jobClass, $range),
            'incidents' => JobDetail::incidents($jobClass),
            'runs' => JobDetail::recentRuns($jobClass),
            'history' => JobTimeline::history()[$jobClass] ?? JobTimeline::idle(),
            'dates' => Days::history(),
        ]);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $history
     * @return list<array<string, mixed>>
     */
    private function board(array $history, TimeFilter $filter): array
    {
        return array_map(fn (array $job): array => [
            ...$job,
            'url' => $this->jobUrl($job['token'], $filter),
            'history' => $history[$job['job_class']] ?? JobTimeline::idle(),
        ], JobBoard::rows($filter->range()));
    }

    /**
     * @param  array<string, list<array{runs: int, failed: int, released: int}>>  $history
     * @return list<array<string, mixed>>
     */
    private function matrix(array $history, TimeFilter $filter): array
    {
        return collect($history)
            ->map(fn (array $days, string $jobClass): array => [...$this->row($jobClass, $days, $filter), 'history' => $days])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function timeline(CarbonImmutable $date, TimeFilter $filter): array
    {
        $day = JobTimeline::day($date);

        return [
            ...Days::header($date),
            'now_hour' => CarbonImmutable::now()->hour,
            'hours' => $day['hours'],
            'jobs' => collect($day['jobs'])
                ->map(fn (array $hours, string $jobClass): array => [...$this->row($jobClass, $hours, $filter), 'hours' => $hours])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<array{runs: int, failed: int, released: int}>  $cells
     * @return array<string, mixed>
     */
    private function row(string $jobClass, array $cells, TimeFilter $filter): array
    {
        $identity = JobIdentity::of($jobClass);

        return [
            ...$identity,
            'url' => $this->jobUrl($identity['token'], $filter),
            'runs' => array_sum(array_column($cells, 'runs')),
            'failed' => array_sum(array_column($cells, 'failed')),
            'released' => array_sum(array_column($cells, 'released')),
        ];
    }

    private function jobUrl(string $token, TimeFilter $filter): string
    {
        return route('ops.jobs.show', ['token' => $token, ...$filter->params()]);
    }
}
