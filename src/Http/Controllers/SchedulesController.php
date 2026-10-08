<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Response;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Ui\Reports\Jobs\Days;
use Phattarachai\OpsLaravel\Ui\Reports\Schedules\ScheduleBoard;
use Phattarachai\OpsLaravel\Ui\Reports\Schedules\ScheduleDetail;
use Phattarachai\OpsLaravel\Ui\Reports\Schedules\ScheduledTasks;
use Phattarachai\OpsLaravel\Ui\Reports\Schedules\ScheduleTimeline;
use Phattarachai\OpsLaravel\Ui\TimeFilter;
use Phattarachai\OpsLaravel\Ui\UiPage;

final class SchedulesController
{
    /** @var list<string> */
    private const array MODES = ['list', 'timeline', 'matrix'];

    public function index(Request $request): Response
    {
        $filter = TimeFilter::fromRequest($request);
        $mode = in_array($request->query('view'), self::MODES, true) ? (string) $request->query('view') : 'list';
        $tasks = $this->tasks($filter);

        return UiPage::render('schedules', [
            'mode' => $mode,
            'time_filter' => $filter->toProps($this->latest()),
            'tasks' => $tasks,
            'dates' => Days::history(),
            'timeline' => $mode === 'timeline' ? $this->timeline(Days::pick($request->query('day')), $tasks) : null,
        ]);
    }

    public function show(Request $request, string $token): Response
    {
        $task = ScheduledTasks::decode($token) ?? abort(404);
        $filter = TimeFilter::fromRequest($request);
        $range = $filter->range();

        return UiPage::render('schedules.show', [
            'time_filter' => $filter->toProps($this->latest($task)),
            'index_url' => route('ops.schedules', $filter->params()),
            'task' => $task,
            'name' => class_basename($task),
            'summary' => ScheduleBoard::task($task, $range),
            'buckets' => ScheduleDetail::buckets($task, $range),
            'runs' => ScheduleDetail::recentRuns($task),
            'incidents' => ScheduleDetail::incidents($task),
            'history' => ScheduleTimeline::history()[$task] ?? [],
            'dates' => Days::history(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tasks(TimeFilter $filter): array
    {
        $history = ScheduleTimeline::history();

        return array_map(fn (array $task): array => [
            ...$task,
            'url' => route('ops.schedules.show', ['token' => $task['token'], ...$filter->params()]),
            'history' => $history[$task['task']] ?? [],
        ], ScheduleBoard::tasks($filter->range()));
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return array<string, mixed>
     */
    private function timeline(CarbonImmutable $date, array $tasks): array
    {
        return [...Days::header($date), 'tasks' => ScheduleTimeline::day($date, $tasks)];
    }

    private function latest(?string $task = null): ?CarbonImmutable
    {
        $latest = ScheduleRun::query()->when($task !== null, fn ($query) => $query->where('task', $task))->max('started_at');

        return $latest === null ? null : CarbonImmutable::parse((string) $latest);
    }
}
