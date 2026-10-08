<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Overview;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Phattarachai\OpsCore\Alerts\IncidentStatus;
use Phattarachai\OpsCore\Alerts\Severity;
use Phattarachai\OpsCore\Health\HealthClassifier;
use Phattarachai\OpsLaravel\Enums\OutgoingOutcome;
use Phattarachai\OpsLaravel\Enums\ScheduleRunStatus;
use Phattarachai\OpsLaravel\Models\Incident;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Models\OutgoingRollup;
use Phattarachai\OpsLaravel\Models\QueueSample;
use Phattarachai\OpsLaravel\Models\RequestRollup;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Models\SignalRollup;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Signals\BuiltInSignals;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\TimeFilter;
use Phattarachai\OpsLaravel\Ui\TimeRange;
use Throwable;

/**
 * "Is anything broken right now, and where?" — one status block per area (health, three
 * numbers, trend bars) plus every failing item across areas, from the same tables the area
 * pages read. Host blocks from Ops::overviewBlocks() are appended.
 */
final readonly class OverviewReport
{
    private TimeRange $range;

    public function __construct(private TimeFilter $filter)
    {
        $this->range = $filter->range();
    }

    /** @return array{areas: list<array<string, mixed>>, attention: list<array<string, mixed>>} */
    public function props(): array
    {
        $areas = collect(OpsCache::remember("overview:{$this->range->key}", fn (): array => [
            $this->incidents(),
            $this->incoming(),
            $this->outgoing(),
            $this->jobs(),
            $this->schedules(),
            $this->system(),
        ]))->concat($this->hostBlocks())->values();

        return [
            'areas' => $areas->map(fn (array $area): array => collect($area)->except('attention')->all())->all(),
            'attention' => $areas
                ->flatMap(fn (array $area): array => $area['attention'] ?? [])
                ->sortBy(fn (array $row): int => $row['tone'] === 'danger' ? 0 : 1)
                ->values()
                ->all(),
        ];
    }

    public function dataUpdatedAt(): ?CarbonImmutable
    {
        $stamps = array_filter([
            RequestRollup::query()->where('grain', 'minute')->max('bucket'),
            OutgoingRollup::query()->where('grain', 'minute')->max('bucket'),
            JobRun::query()->max('started_at'),
        ]);

        return $stamps === [] ? null : CarbonImmutable::parse((string) max($stamps));
    }

    /** @return array<string, mixed> */
    private function incidents(): array
    {
        $open = Incident::query()->with('rule')->where('status', IncidentStatus::Open)->latest('opened_at')->get();
        $critical = $open->where('severity', Severity::Critical)->count();

        return [
            'key' => 'incidents',
            'title' => 'Incidents',
            'url' => route('ops.incidents'),
            'health' => match (true) {
                $critical > 0 => $this->health('danger', 'Critical'),
                $open->isNotEmpty() => $this->health('warning', 'Open'),
                default => $this->health('success', 'Clear'),
            },
            'stats' => [
                ['label' => 'Open', 'value' => number_format($open->count()), 'tone' => $open->isNotEmpty() ? 'warn' : null],
                ['label' => 'Critical', 'value' => number_format($critical), 'tone' => $critical > 0 ? 'fail' : null],
                ['label' => 'Opened in window', 'value' => number_format(Incident::query()->where('opened_at', '>=', $this->range->start)->count())],
            ],
            'chart' => ['kind' => 'runs', 'span' => $this->range->label, 'series' => []],
            'attention' => $open->map(fn (Incident $incident): array => $this->row(
                'Incidents',
                trim(($incident->rule->name ?? 'Rule').' · '.$incident->key, ' ·'),
                $incident->severity === Severity::Critical ? 'danger' : 'warning',
                $incident->severity === Severity::Critical ? 'Critical' : 'Warning',
                (string) Format::date($incident->opened_at, 'j M H:i'),
                route('ops.incidents.show', ['token' => $incident->id]),
            ))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function incoming(): array
    {
        $rows = RequestRollup::query()->where('grain', $this->range->grain)->where('bucket', '>=', $this->range->start)->where('bucket', '<', $this->range->end)->get();
        $requests = (int) $rows->sum('requests');
        $serverErrors = (int) $rows->where('status', '>=', 500)->sum('requests');
        $limited = (int) $rows->where('status', 429)->sum('requests');

        return [
            'key' => 'incoming',
            'title' => 'Incoming',
            'url' => route('ops.requests', $this->filter->params()),
            'health' => match (true) {
                $requests === 0 => $this->health('gray', 'No data'),
                $serverErrors > 0 => $this->health('warning', '5xx'),
                default => $this->health('success', 'Healthy'),
            },
            'stats' => [
                ['label' => 'Requests', 'value' => Format::count($requests)],
                ['label' => '5xx', 'value' => number_format($serverErrors), 'tone' => $serverErrors > 0 ? 'fail' : null],
                ['label' => '429', 'value' => number_format($limited), 'tone' => $limited > 0 ? 'warn' : null],
            ],
            'chart' => ['kind' => 'status', 'span' => $this->range->label, 'series' => $this->bars($rows, fn (Collection $bar): array => [
                'ok' => (int) $bar->where('status', '<', 400)->sum('requests'),
                's4xx' => (int) $bar->where('status', '>=', 400)->where('status', '<', 500)->where('status', '!=', 429)->sum('requests'),
                's429' => (int) $bar->where('status', 429)->sum('requests'),
                's5xx' => (int) $bar->where('status', '>=', 500)->sum('requests'),
            ])],
            'attention' => $serverErrors === 0 ? [] : [$this->row('Incoming', '5xx', 'warning', '5xx', number_format($serverErrors), route('ops.requests', $this->filter->params()))],
        ];
    }

    /** @return array<string, mixed> */
    private function outgoing(): array
    {
        $rows = OutgoingRollup::query()->where('grain', $this->range->grain)->where('bucket', '>=', $this->range->start)->where('bucket', '<', $this->range->end)->get();
        $calls = (int) $rows->sum('calls');
        $failed = (int) $rows->where('outcome', '!=', OutgoingOutcome::Ok->value)->sum('calls');
        $classifier = new HealthClassifier;
        $failing = $rows->groupBy('endpoint')
            ->map(fn (Collection $endpoint, string $name): array => ['name' => $name, 'calls' => (int) $endpoint->sum('calls'), 'failed' => (int) $endpoint->where('outcome', '!=', OutgoingOutcome::Ok->value)->sum('calls')])
            ->filter(fn (array $endpoint): bool => $classifier->forFailureRate($endpoint['calls'], $endpoint['failed'])->value === 'incident');

        return [
            'key' => 'outgoing',
            'title' => 'Outgoing',
            'url' => route('ops.outgoing', $this->filter->params()),
            'health' => match (true) {
                $calls === 0 => $this->health('gray', 'No data'),
                $failing->isNotEmpty() => $this->health('danger', 'Failing'),
                $failed > 0 => $this->health('warning', 'Errors'),
                default => $this->health('success', 'Healthy'),
            },
            'stats' => [
                ['label' => 'Calls', 'value' => Format::count($calls)],
                ['label' => 'Failed', 'value' => number_format($failed), 'tone' => $failed > 0 ? 'fail' : null],
                ['label' => 'Error %', 'value' => Format::percent($failed, $calls)],
            ],
            'chart' => ['kind' => 'outcome', 'span' => $this->range->label, 'series' => $this->bars($rows, fn (Collection $bar): array => [
                'ok' => (int) $bar->where('outcome', OutgoingOutcome::Ok->value)->sum('calls'),
                'timeout' => (int) $bar->whereIn('outcome', [OutgoingOutcome::Timeout->value, OutgoingOutcome::ConnectError->value])->sum('calls'),
                'ssl' => 0,
                'http_error' => (int) $bar->whereIn('outcome', [OutgoingOutcome::Http4xx->value, OutgoingOutcome::Http5xx->value])->sum('calls'),
            ])],
            'attention' => $failing->map(fn (array $endpoint): array => $this->row('Outgoing', $endpoint['name'], 'danger', 'Failing', Format::percent($endpoint['failed'], $endpoint['calls']), route('ops.outgoing.show', ['token' => Format::base64url($endpoint['name'])] + $this->filter->params())))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function jobs(): array
    {
        $runs = JobRun::query()->where('started_at', '>=', $this->range->start)->where('started_at', '<', $this->range->end)->get(['job_class', 'status', 'started_at']);
        $failures = $runs->filter(fn (JobRun $run): bool => $run->status->isFailure());
        $classifier = new HealthClassifier;
        $failingClasses = $runs->groupBy('job_class')
            ->map(fn (Collection $class, string $name): array => ['name' => $name, 'runs' => $class->count(), 'failed' => $class->filter(fn (JobRun $run): bool => $run->status->isFailure())->count()])
            ->filter(fn (array $class): bool => $class['failed'] > 0)
            ->sortByDesc('failed');

        return [
            'key' => 'jobs',
            'title' => 'Jobs',
            'url' => route('ops.jobs', $this->filter->params()),
            'health' => match (true) {
                $runs->isEmpty() => $this->health('gray', 'No data'),
                $failingClasses->contains(fn (array $class): bool => $classifier->forFailureRate($class['runs'], $class['failed'])->value === 'incident') => $this->health('danger', 'Failing'),
                $failingClasses->isNotEmpty() => $this->health('warning', 'Degrading'),
                default => $this->health('success', 'Healthy'),
            },
            'stats' => [
                ['label' => 'Runs', 'value' => Format::count($runs->count())],
                ['label' => 'Failed', 'value' => number_format($failures->count()), 'tone' => $failures->isNotEmpty() ? 'fail' : null],
                ['label' => 'Classes failing', 'value' => number_format($failingClasses->count())],
            ],
            'chart' => ['kind' => 'runs', 'span' => $this->range->label, 'series' => $this->bars($runs, fn (Collection $bar): array => [
                'ok' => $bar->reject(fn (JobRun $run): bool => $run->status->isFailure())->count(),
                'failed' => $bar->filter(fn (JobRun $run): bool => $run->status->isFailure())->count(),
            ], 'started_at')],
            'attention' => $failingClasses->take(10)->map(fn (array $class): array => $this->row('Jobs', class_basename($class['name']), $classifier->forFailureRate($class['runs'], $class['failed'])->value === 'incident' ? 'danger' : 'warning', 'Failed', "{$class['failed']} / {$class['runs']}", route('ops.jobs.show', ['token' => Format::base64url($class['name'])] + $this->filter->params())))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function schedules(): array
    {
        $runs = ScheduleRun::query()->where('started_at', '>=', $this->range->start)->where('started_at', '<', $this->range->end)->where('status', '!=', ScheduleRunStatus::Skipped)->get(['task', 'status', 'started_at']);
        $failed = $runs->where('status', ScheduleRunStatus::Failed);
        $failingTasks = $failed->groupBy('task')->map(fn (Collection $task, string $name): array => ['name' => $name, 'failed' => $task->count()]);

        return [
            'key' => 'schedules',
            'title' => 'Schedules',
            'url' => route('ops.schedules', $this->filter->params()),
            'health' => match (true) {
                $runs->isEmpty() => $this->health('gray', 'No data'),
                $failingTasks->isNotEmpty() => $this->health('danger', 'Failing'),
                default => $this->health('success', 'Healthy'),
            },
            'stats' => [
                ['label' => 'Runs', 'value' => Format::count($runs->count())],
                ['label' => 'Failed', 'value' => number_format($failed->count()), 'tone' => $failed->isNotEmpty() ? 'fail' : null],
                ['label' => 'Tasks failing', 'value' => number_format($failingTasks->count())],
            ],
            'chart' => ['kind' => 'runs', 'span' => $this->range->label, 'series' => $this->bars($runs, fn (Collection $bar): array => [
                'ok' => $bar->where('status', '!=', ScheduleRunStatus::Failed)->count(),
                'failed' => $bar->where('status', ScheduleRunStatus::Failed)->count(),
            ], 'started_at')],
            'attention' => $failingTasks->map(fn (array $task): array => $this->row('Schedules', $task['name'], 'danger', 'Failed', (string) $task['failed'], route('ops.schedules.show', ['token' => Format::base64url($task['name'])] + $this->filter->params())))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function system(): array
    {
        $latestBucket = QueueSample::query()->max('bucket');
        $backlog = $latestBucket === null ? 0 : (int) QueueSample::query()->where('bucket', $latestBucket)->sum('size');
        $exceptions = (int) SignalRollup::query()->where('signal', BuiltInSignals::EXCEPTION)->where('bucket', '>=', $this->range->start)->sum('count');

        return [
            'key' => 'system',
            'title' => 'System',
            'url' => route('ops.system', $this->filter->params()),
            'health' => match (true) {
                $exceptions > 0 => $this->health('warning', 'Exceptions'),
                default => $this->health('success', 'Healthy'),
            },
            'stats' => [
                ['label' => 'Queued now', 'value' => number_format($backlog), 'tone' => $backlog > 0 ? 'warn' : null],
                ['label' => 'Exceptions', 'value' => number_format($exceptions), 'tone' => $exceptions > 0 ? 'fail' : null],
                ['label' => 'Window', 'value' => $this->range->label],
            ],
            'chart' => ['kind' => 'runs', 'span' => $this->range->label, 'series' => []],
            'attention' => [],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function hostBlocks(): array
    {
        $blocks = [];

        foreach (Ops::overviewBlockCallbacks() as $callback) {
            try {
                $blocks = [...$blocks, ...$callback($this->range)];
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $blocks;
    }

    /**
     * @template TModel of \Phattarachai\OpsLaravel\Models\OpsModel
     *
     * @param  Collection<int, TModel>  $rows
     * @param  callable(Collection<int, TModel>): array<string, int>  $reduce
     * @return list<array<string, int>>
     */
    private function bars(Collection $rows, callable $reduce, string $column = 'bucket'): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $first = $this->range->firstBarStart();
        $grouped = $rows->groupBy(fn ($row): int => (int) floor($first->diffInMinutes(CarbonImmutable::parse($row->getAttribute($column))) / $this->range->step));

        return array_map(fn (int $index): array => $reduce($grouped->get($index, collect())), range(0, $this->range->bars - 1));
    }

    /** @return array{tone: string, label: string} */
    private function health(string $tone, string $label): array
    {
        return ['tone' => $tone, 'label' => $label];
    }

    /** @return array{area: string, name: string, tone: string, label: string, detail: string, url: string} */
    private function row(string $area, string $name, string $tone, string $label, string $detail, string $url): array
    {
        return ['area' => $area, 'name' => $name, 'tone' => $tone, 'label' => $label, 'detail' => $detail, 'url' => $url];
    }
}
