<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Enums;

enum ScheduleRunStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
