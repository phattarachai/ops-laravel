<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Models;

/**
 * @property string $key
 * @property string|null $value
 */
final class Setting extends OpsModel
{
    protected $table = 'ops_settings';
}
