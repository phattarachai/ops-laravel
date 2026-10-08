<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Recorders;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Str;
use Phattarachai\OpsLaravel\Enums\JobRunStatus;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use Phattarachai\OpsLaravel\Support\OpsContext;
use Throwable;

/**
 * Exactly one ops_job_runs row per attempt: buffered on start, written once on the terminal
 * event, so a sync failure's exception + failed pair yields one row.
 */
class JobRecorder
{
    private const string MODEL_IDENTIFIER = '/ModelIdentifier":\d+:\{s:5:"class";s:\d+:"(?<class>[^"]+)";s:2:"id";(?:s:\d+:"(?<string>[^"]*)"|i:(?<int>\d+));/';

    /** @var array<string, array{uuid: string|null, job_class: string, queue: string|null, connection: string, attempts: int, started_at: CarbonInterface, origin: string|null, parent_uuid: string|null, schedule_run_id: int|null, wait_ms: int|null, domain_type: string|null, domain_id: string|null}> */
    protected array $pending = [];

    public function __construct(protected OpsSettings $settings) {}

    public function started(JobProcessing $event): void
    {
        $this->guard(function () use ($event): void {
            $job = $event->job;

            if (in_array($job->resolveName(), $this->settings->list('recorders.jobs.ignore'), true)) {
                return;
            }

            $this->pending[$this->key($job)] = $this->startMeta($job, $event->connectionName, now());

            OpsContext::enterJob($job->uuid());
        });
    }

    public function succeeded(JobProcessed $event): void
    {
        $this->finalize($event->job, $event->job->isReleased() ? JobRunStatus::Released : JobRunStatus::Success);
    }

    public function failed(JobExceptionOccurred|JobFailed $event): void
    {
        $status = $event->exception instanceof TimeoutExceededException ? JobRunStatus::TimedOut : JobRunStatus::Failed;

        $this->finalize($event->job, $status, $event->exception);
    }

    public function timedOut(JobTimedOut $event): void
    {
        $this->finalize($event->job, JobRunStatus::TimedOut, new TimeoutExceededException("Timed out after {$event->timeout}s"));
    }

    protected function finalize(Job $job, JobRunStatus $status, ?Throwable $exception = null): void
    {
        $this->guard(function () use ($job, $status, $exception): void {
            $meta = $this->pending[$this->key($job)] ?? null;

            if ($meta === null) {
                return;
            }

            unset($this->pending[$this->key($job)]);
            OpsContext::enterJob($meta['parent_uuid']);

            $this->store($meta, $status, $exception);
        });
    }

    /** @param  array{uuid: string|null, job_class: string, queue: string|null, connection: string, attempts: int, started_at: CarbonInterface, origin: string|null, parent_uuid: string|null, schedule_run_id: int|null, wait_ms: int|null, domain_type: string|null, domain_id: string|null}  $meta */
    protected function store(array $meta, JobRunStatus $status, ?Throwable $exception): void
    {
        $startedAt = $meta['started_at'];
        $finishedAt = now();

        JobRun::query()->create([
            ...$meta,
            'status' => $status,
            'finished_at' => $finishedAt,
            'duration_ms' => (int) $startedAt->diffInMilliseconds($finishedAt),
            'exception_class' => $exception instanceof Throwable ? $exception::class : null,
            'exception_message' => $exception instanceof Throwable ? Str::limit($exception->getMessage(), 1000) : null,
        ]);
    }

    /** @return array{uuid: string|null, job_class: string, queue: string|null, connection: string, attempts: int, started_at: CarbonInterface, origin: string|null, parent_uuid: string|null, schedule_run_id: int|null, wait_ms: int|null, domain_type: string|null, domain_id: string|null} */
    protected function startMeta(Job $job, string $connection, CarbonInterface $startedAt): array
    {
        [$domainType, $domainId] = $this->domainOf($job);

        return [
            'uuid' => $job->uuid(),
            'job_class' => $job->resolveName(),
            'queue' => $job->getQueue(),
            'connection' => $connection,
            'attempts' => $job->attempts(),
            'started_at' => $startedAt,
            'origin' => OpsContext::origin(),
            'parent_uuid' => OpsContext::jobUuid(),
            'schedule_run_id' => OpsContext::scheduleRunId(),
            'wait_ms' => $this->waitMs($job, $startedAt),
            'domain_type' => $domainType,
            'domain_id' => $domainId,
        ];
    }

    protected function waitMs(Job $job, CarbonInterface $startedAt): ?int
    {
        $payload = $job->payload();
        $dispatchedAt = $payload['pushedAt'] ?? $payload['createdAt'] ?? null;

        if ($job->attempts() > 1 || ! is_numeric($dispatchedAt)) {
            return null;
        }

        $delaySeconds = (int) ($payload['delay'] ?? 0);

        return max(0, (int) round(((float) $startedAt->format('U.u') - (float) $dispatchedAt - $delaySeconds) * 1000));
    }

    /** @return array{0: ?string, 1: ?string} */
    protected function domainOf(Job $job): array
    {
        $command = $job->payload()['data']['command'] ?? null;

        if (! is_string($command) || ! preg_match(self::MODEL_IDENTIFIER, $command, $match)) {
            return [null, null];
        }

        return [class_basename($match['class']), $match['string'] !== '' ? $match['string'] : $match['int']];
    }

    protected function key(Job $job): string
    {
        return $job->uuid() ?? (string) $job->getJobId();
    }

    protected function guard(callable $callback): void
    {
        try {
            if (Ops::recording() && $this->settings->bool('recorders.jobs.enabled')) {
                $callback();
            }
        } catch (Throwable) {
        }
    }
}
