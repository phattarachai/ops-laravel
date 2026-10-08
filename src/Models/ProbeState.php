<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Models;

use Carbon\CarbonImmutable;
use Override;
use Phattarachai\OpsCore\Alerts\ProbeLevel;

/**
 * @property string $signal
 * @property string $key
 * @property ProbeLevel $level
 * @property float|null $value
 * @property string|null $message
 * @property CarbonImmutable $observed_at
 */
final class ProbeState extends OpsModel
{
    protected $table = 'ops_probe_states';

    #[Override]
    protected function casts(): array
    {
        return ['level' => ProbeLevel::class, 'value' => 'float', 'observed_at' => 'immutable_datetime'];
    }
}
