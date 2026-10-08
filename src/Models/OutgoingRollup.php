<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Override;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

/**
 * @property string $grain
 * @property CarbonImmutable $bucket
 * @property string $endpoint
 * @property string $method
 * @property int $status
 * @property string $outcome
 * @property int $calls
 * @property int $timed
 * @property int $sum_ms
 * @property int $max_ms
 * @property int|null $p50_ms
 * @property int|null $p95_ms
 * @property list<int> $histogram
 */
final class OutgoingRollup extends OpsModel
{
    use MassPrunable;

    protected $table = 'ops_outgoing_rollups';

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        $settings = app(OpsSettings::class);

        return self::query()->where(fn (Builder $query) => $query
            ->where(fn (Builder $minute) => $minute->where('grain', 'minute')->where('bucket', '<', now()->subDays($settings->int('retention.minute_days', 7))))
            ->orWhere(fn (Builder $hour) => $hour->where('grain', 'hour')->where('bucket', '<', now()->subDays($settings->int('retention.hour_days', 365)))));
    }

    #[Override]
    protected function casts(): array
    {
        return ['bucket' => 'immutable_datetime', 'histogram' => 'array', 'status' => 'integer'];
    }
}
