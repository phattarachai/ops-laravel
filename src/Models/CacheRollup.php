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
 * @property string $key
 * @property int $hits
 * @property int $misses
 */
final class CacheRollup extends OpsModel
{
    use MassPrunable;

    protected $table = 'ops_cache_rollups';

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('bucket', '<', now()->subDays(app(OpsSettings::class)->int('retention.user_days', 7)));
    }

    #[Override]
    protected function casts(): array
    {
        return ['bucket' => 'immutable_datetime'];
    }
}
