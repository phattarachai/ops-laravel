<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Override;

/**
 * @property string $stream
 * @property CarbonImmutable $bucket
 */
final class RollupLedger extends OpsModel
{
    use MassPrunable;

    public const null UPDATED_AT = null;

    protected $table = 'ops_rollup_ledger';

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('bucket', '<', now()->subDays(2));
    }

    #[Override]
    protected function casts(): array
    {
        return ['bucket' => 'immutable_datetime'];
    }
}
