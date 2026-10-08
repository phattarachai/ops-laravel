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
 * @property string $signal
 * @property string $key
 * @property string $field
 * @property string $value
 */
final class SignalValue extends OpsModel
{
    use MassPrunable;

    protected $table = 'ops_signal_values';

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
