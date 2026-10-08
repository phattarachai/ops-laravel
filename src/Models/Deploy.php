<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Models;

use Carbon\CarbonImmutable;
use Override;

/**
 * @property string $release
 * @property string|null $description
 * @property CarbonImmutable $deployed_at
 */
final class Deploy extends OpsModel
{
    protected $table = 'ops_deploys';

    #[Override]
    protected function casts(): array
    {
        return ['deployed_at' => 'immutable_datetime'];
    }
}
