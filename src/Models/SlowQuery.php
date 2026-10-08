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
 * @property string $hash
 * @property string $connection
 * @property string $sql
 * @property string|null $location
 * @property int $count
 * @property int $sum_ms
 * @property int $max_ms
 */
final class SlowQuery extends OpsModel
{
    use MassPrunable;

    protected $table = 'ops_slow_queries';

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('bucket', '<', now()->subDays(app(OpsSettings::class)->int('retention.signal_days', 7)));
    }

    #[Override]
    protected function casts(): array
    {
        return ['bucket' => 'immutable_datetime'];
    }
}
