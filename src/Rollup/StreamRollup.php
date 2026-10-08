<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Rollup;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Phattarachai\OpsLaravel\Buffer\MinuteBuffer;
use Phattarachai\OpsLaravel\Buffer\MinuteData;
use Phattarachai\OpsLaravel\Models\RollupLedger;

/**
 * Rolls one buffer stream's closed minute into its tables exactly once: the ledger row is
 * claimed inside the same transaction as the writes, so a rerun of the minute is a no-op.
 */
abstract class StreamRollup
{
    public function __construct(protected MinuteBuffer $buffer) {}

    abstract public function stream(): string;

    public function foldHours(CarbonImmutable $fromHour, CarbonImmutable $toHour): int
    {
        return 0;
    }

    public function roll(CarbonImmutable $minute): bool
    {
        $data = $this->read($minute);

        if ($data->isEmpty()) {
            return false;
        }

        $rolled = $this->claimAndWrite($minute, $data);
        $this->forget($minute);

        return $rolled;
    }

    abstract protected function write(CarbonImmutable $minute, MinuteData $data): void;

    protected function read(CarbonImmutable $minute): MinuteData
    {
        return $this->buffer->read($this->stream(), $minute);
    }

    protected function forget(CarbonImmutable $minute): void
    {
        $this->buffer->forget($this->stream(), $minute);
    }

    private function claimAndWrite(CarbonImmutable $minute, MinuteData $data): bool
    {
        try {
            return RollupLedger::query()->getConnection()->transaction(function () use ($minute, $data): bool {
                RollupLedger::query()->create(['stream' => $this->stream(), 'bucket' => $minute]);
                $this->write($minute, $data);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
