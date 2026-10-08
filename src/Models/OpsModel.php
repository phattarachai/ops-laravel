<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

abstract class OpsModel extends Model
{
    protected $guarded = [];

    #[Override]
    public function getConnectionName(): ?string
    {
        return config('ops.connection') ?? parent::getConnectionName();
    }
}
