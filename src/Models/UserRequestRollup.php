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
 * @property string $route
 * @property string $user_id
 * @property string $segment
 * @property int $requests
 * @property int $status_4xx
 * @property int $status_429
 * @property int $status_5xx
 * @property int $sum_ms
 * @property int $peak_per_minute
 */
final class UserRequestRollup extends OpsModel
{
    use MassPrunable;

    protected $table = 'ops_http_user_rollups';

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('bucket', '<', now()->subDays(app(OpsSettings::class)->int('retention.user_days', 30)));
    }

    #[Override]
    protected function casts(): array
    {
        return ['bucket' => 'immutable_datetime', 'user_id' => 'string'];
    }
}
