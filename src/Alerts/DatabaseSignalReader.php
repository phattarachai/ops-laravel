<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Alerts;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Phattarachai\OpsCore\Alerts\Contracts\SignalReader;
use Phattarachai\OpsCore\Alerts\ProbeReading;
use Phattarachai\OpsLaravel\Enums\JobRunStatus;
use Phattarachai\OpsLaravel\Enums\OutgoingOutcome;
use Phattarachai\OpsLaravel\Enums\ScheduleRunStatus;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Models\OutgoingRollup;
use Phattarachai\OpsLaravel\Models\ProbeState;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Models\SignalRollup;
use Phattarachai\OpsLaravel\Models\SignalValue;
use Phattarachai\OpsLaravel\Signals\BuiltInSignals;

/**
 * Built-in signals read the telemetry tables they come from; every other name reads the
 * rolled ops_signal_* minutes. Windows are minute-aligned, since signals roll per minute.
 */
final class DatabaseSignalReader implements SignalReader
{
    public function countsByKey(string $signal, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        [$from, $to] = [CarbonImmutable::instance($from), CarbonImmutable::instance($to)];

        return match ($signal) {
            BuiltInSignals::JOB_RUN => $this->jobCounts($from, $to, failedOnly: false),
            BuiltInSignals::JOB_FAILED => $this->jobCounts($from, $to, failedOnly: true),
            BuiltInSignals::SCHEDULE_RUN => $this->scheduleCounts($from, $to, failedOnly: false),
            BuiltInSignals::SCHEDULE_FAILED => $this->scheduleCounts($from, $to, failedOnly: true),
            BuiltInSignals::OUTGOING_CALL => $this->outgoingCounts($from, $to, failedOnly: false),
            BuiltInSignals::OUTGOING_FAILED => $this->outgoingCounts($from, $to, failedOnly: true),
            default => $this->signalCounts($signal, $from, $to),
        };
    }

    public function distinctCountsByKey(string $signal, string $field, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->pairs(SignalValue::query()
            ->where('signal', $signal)
            ->where('field', $field)
            ->whereBetween('bucket', $this->minuteWindow(CarbonImmutable::instance($from), CarbonImmutable::instance($to)))
            ->selectRaw($this->wrap('key').', count(distinct '.$this->wrap('value').') as total')
            ->groupBy('key'), 'key');
    }

    public function lastSeenByKey(string $signal): array
    {
        $rows = match ($signal) {
            BuiltInSignals::SCHEDULE_RUN => ScheduleRun::query()->where('status', '!=', ScheduleRunStatus::Skipped)->selectRaw('task as k, max(started_at) as last')->groupBy('task'),
            BuiltInSignals::JOB_RUN => JobRun::query()->selectRaw('job_class as k, max(started_at) as last')->groupBy('job_class'),
            default => SignalRollup::query()->where('signal', $signal)->selectRaw($this->wrap('key').' as k, max(bucket) as last')->groupBy('key'),
        };

        return $rows->toBase()->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->k => CarbonImmutable::parse((string) $row->last)->toDateTimeImmutable()])
            ->all();
    }

    public function statesByKey(string $signal): array
    {
        return ProbeState::query()
            ->where('signal', $signal)
            ->get()
            ->mapWithKeys(fn (ProbeState $state): array => [
                $state->key => new ProbeReading($state->level, $state->observed_at->toDateTimeImmutable(), $state->value, $state->message),
            ])
            ->all();
    }

    /** @return array<string, int> */
    private function signalCounts(string $signal, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->pairs(SignalRollup::query()
            ->where('signal', $signal)
            ->whereBetween('bucket', $this->minuteWindow($from, $to))
            ->selectRaw($this->wrap('key').', sum('.$this->wrap('count').') as total')
            ->groupBy('key'), 'key');
    }

    /** @return array<string, int> */
    private function jobCounts(CarbonImmutable $from, CarbonImmutable $to, bool $failedOnly): array
    {
        return $this->pairs(JobRun::query()
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $to)
            ->when($failedOnly, fn (Builder $query) => $query->whereIn('status', [JobRunStatus::Failed, JobRunStatus::TimedOut]))
            ->when(! $failedOnly, fn (Builder $query) => $query->where('status', '!=', JobRunStatus::Released))
            ->selectRaw('job_class, count(*) as total')
            ->groupBy('job_class'), 'job_class');
    }

    /** @return array<string, int> */
    private function scheduleCounts(CarbonImmutable $from, CarbonImmutable $to, bool $failedOnly): array
    {
        return $this->pairs(ScheduleRun::query()
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $to)
            ->when($failedOnly, fn (Builder $query) => $query->where('status', ScheduleRunStatus::Failed))
            ->when(! $failedOnly, fn (Builder $query) => $query->where('status', '!=', ScheduleRunStatus::Skipped))
            ->selectRaw('task, count(*) as total')
            ->groupBy('task'), 'task');
    }

    /** @return array<string, int> */
    private function outgoingCounts(CarbonImmutable $from, CarbonImmutable $to, bool $failedOnly): array
    {
        return $this->pairs(OutgoingRollup::query()
            ->where('grain', 'minute')
            ->whereBetween('bucket', $this->minuteWindow($from, $to))
            ->when($failedOnly, fn (Builder $query) => $query->where('outcome', '!=', OutgoingOutcome::Ok->value))
            ->selectRaw('endpoint, sum(calls) as total')
            ->groupBy('endpoint'), 'endpoint');
    }

    private function wrap(string $column): string
    {
        return SignalRollup::query()->getQuery()->getGrammar()->wrap($column);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function minuteWindow(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [$from->startOfMinute(), $to->subMinute()->startOfMinute()];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return array<string, int>
     */
    private function pairs(Builder $query, string $column): array
    {
        /** @var Collection<int, object> $rows */
        $rows = $query->toBase()->get();

        return $rows->mapWithKeys(fn (object $row): array => [(string) $row->{$column} => (int) $row->total])->all();
    }
}
