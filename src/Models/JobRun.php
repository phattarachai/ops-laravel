<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Override;
use Phattarachai\OpsLaravel\Enums\JobRunStatus;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

/**
 * @property string|null $uuid
 * @property string $job_class
 * @property string|null $queue
 * @property string|null $connection
 * @property int $attempts
 * @property JobRunStatus $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property int|null $duration_ms
 * @property int|null $wait_ms
 * @property string|null $exception_class
 * @property string|null $exception_message
 * @property string|null $domain_type
 * @property string|null $domain_id
 * @property string|null $origin
 * @property string|null $parent_uuid
 * @property int|null $schedule_run_id
 */
final class JobRun extends OpsModel
{
    use MassPrunable;

    protected $table = 'ops_job_runs';

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('started_at', '<', now()->subDays(app(OpsSettings::class)->int('retention.runs_days', 30)));
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'status' => JobRunStatus::class,
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
