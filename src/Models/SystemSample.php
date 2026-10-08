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
 * @property string $host
 * @property float|null $load_1
 * @property int|null $cpus
 * @property float|null $memory_used_pct
 * @property float|null $disk_used_pct
 */
final class SystemSample extends OpsModel
{
    use MassPrunable;

    public $timestamps = false;

    protected $table = 'ops_system_samples';

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('bucket', '<', now()->subDays(app(OpsSettings::class)->int('retention.minute_days', 7)));
    }

    #[Override]
    protected function casts(): array
    {
        return ['bucket' => 'immutable_datetime', 'load_1' => 'float', 'memory_used_pct' => 'float', 'disk_used_pct' => 'float'];
    }
}
