<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Phattarachai\OpsLaravel\Enums\JobRunStatus;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use Phattarachai\OpsLaravel\Support\OpsContext;
use Phattarachai\OpsLaravel\Tests\Fixtures\RecordedJob;

it('writes one row per successful run with its origin', function (): void {
    OpsContext::stampOrigin('api');

    RecordedJob::dispatch();

    expect(JobRun::query()->sole())
        ->job_class->toBe(RecordedJob::class)
        ->status->toBe(JobRunStatus::Success)
        ->origin->toBe('api')
        ->duration_ms->toBeGreaterThanOrEqual(0);
});

it('writes a single failed row for a sync failure', function (): void {
    expect(fn () => RecordedJob::dispatch(fail: true))->toThrow(RuntimeException::class);

    expect(JobRun::query()->sole())
        ->status->toBe(JobRunStatus::Failed)
        ->exception_class->toBe(RuntimeException::class)
        ->exception_message->toContain('timed out');
});

it('leaves no job uuid behind in the context', function (): void {
    RecordedJob::dispatch();

    expect(Context::getHidden('ops.job_uuid'))->toBeNull();
});

it('skips ignored job classes', function (): void {
    app(OpsSettings::class)->set('recorders.jobs.ignore', [RecordedJob::class]);

    RecordedJob::dispatch();

    expect(JobRun::query()->count())->toBe(0);
});
