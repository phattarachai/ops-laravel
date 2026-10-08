<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Enums;

enum JobRunStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Released = 'released';
    case TimedOut = 'timed_out';

    public function isFailure(): bool
    {
        return $this === self::Failed || $this === self::TimedOut;
    }
}
