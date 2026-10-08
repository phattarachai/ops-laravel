<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Override;
use Phattarachai\OpsLaravel\Enums\ScheduleRunStatus;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

/**
 * @property int $id
 * @property string $task
 * @property string $expression
 * @property ScheduleRunStatus $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property int|null $duration_ms
 * @property int|null $exit_code
 * @property string|null $exception_class
 * @property string|null $exception_message
 */
final class ScheduleRun extends OpsModel
{
    use MassPrunable;

    protected $table = 'ops_schedule_runs';

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('started_at', '<', now()->subDays(app(OpsSettings::class)->int('retention.runs_days', 30)));
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'status' => ScheduleRunStatus::class,
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
