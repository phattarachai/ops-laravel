<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Console;

use Illuminate\Console\Command;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Models\OutgoingRollup;
use Phattarachai\OpsLaravel\Models\RequestRollup;
use Phattarachai\OpsLaravel\Models\RollupLedger;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Models\SignalRollup;
use Phattarachai\OpsLaravel\Models\SignalValue;
use Phattarachai\OpsLaravel\Models\UserRequestRollup;

final class PruneCommand extends Command
{
    /** @var list<class-string> */
    public const array MODELS = [
        RequestRollup::class,
        UserRequestRollup::class,
        OutgoingRollup::class,
        JobRun::class,
        ScheduleRun::class,
        SignalRollup::class,
        SignalValue::class,
        RollupLedger::class,
    ];

    protected $signature = 'ops:prune';

    protected $description = 'Delete Ops telemetry past its retention';

    public function handle(): int
    {
        return $this->call('model:prune', ['--model' => self::MODELS]);
    }
}
