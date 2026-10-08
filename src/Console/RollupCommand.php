<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Rollup\StreamRollup;
use Phattarachai\OpsLaravel\Rollup\StreamRollups;

/**
 * Rolls every closed minute after the high-water mark (at most 170 minutes back, inside the
 * buffer TTL) for each stream, refolds the touched hours, then advances the mark.
 */
final class RollupCommand extends Command
{
    public const string HIGH_WATER_KEY = 'ops:rollup:high-water';

    private const int MAX_LOOKBACK_MINUTES = 170;

    protected $signature = 'ops:rollup {--minutes=15 : Lookback when there is no high-water mark}';

    protected $description = 'Roll closed minutes of Ops telemetry from the buffer into the ops_* tables';

    public function handle(StreamRollups $rollups): int
    {
        if (! Ops::recording()) {
            return self::SUCCESS;
        }

        $to = CarbonImmutable::now()->startOfMinute();
        $pending = $this->pendingMinutes($to);
        $rolled = 0;
        $hours = 0;

        foreach ($rollups->all() as $rollup) {
            $rolled += $this->rollMinutes($rollup, $pending);
            $hours += $pending === [] ? 0 : (int) rescue(fn (): int => $rollup->foldHours($pending[0]->startOfHour(), $to->startOfHour()), 0);
        }

        Cache::forever(self::HIGH_WATER_KEY, $to->subMinute()->toDateTimeString());

        $this->components->info("Rolled {$rolled} stream minutes, folded {$hours} hour rows.");

        return self::SUCCESS;
    }

    /** @param  list<CarbonImmutable>  $minutes */
    private function rollMinutes(StreamRollup $rollup, array $minutes): int
    {
        return count(array_filter($minutes, fn (CarbonImmutable $minute): bool => (bool) rescue(fn (): bool => $rollup->roll($minute), false)));
    }

    /** @return list<CarbonImmutable> */
    private function pendingMinutes(CarbonImmutable $to): array
    {
        $highWater = Cache::get(self::HIGH_WATER_KEY);
        $floor = $to->subMinutes(self::MAX_LOOKBACK_MINUTES);
        $from = is_string($highWater)
            ? CarbonImmutable::parse($highWater)->addMinute()
            : $to->subMinutes((int) $this->option('minutes'));

        $minutes = [];

        for ($minute = $from->max($floor); $minute->lt($to); $minute = $minute->addMinute()) {
            $minutes[] = $minute;
        }

        return $minutes;
    }
}
