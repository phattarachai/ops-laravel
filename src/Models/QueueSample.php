<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Override;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

/**
 * @property CarbonImmutable $bucket
 * @property string $connection
 * @property string $queue
 * @property int $size
 * @property int|null $pending
 * @property int|null $delayed
 * @property int|null $reserved
 * @property int|null $oldest_wait_seconds
 */
final class QueueSample extends OpsModel
{
    use MassPrunable;

    public $timestamps = false;

    protected $table = 'ops_queue_samples';

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('bucket', '<', now()->subDays(app(OpsSettings::class)->int('retention.minute_days', 7)));
    }

    #[Override]
    protected function casts(): array
    {
        return ['bucket' => 'immutable_datetime'];
    }
}
