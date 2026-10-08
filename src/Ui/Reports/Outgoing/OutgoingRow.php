<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Outgoing;

use Carbon\CarbonImmutable;
use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsCore\Rollup\Bucket;
use Phattarachai\OpsLaravel\Enums\OutgoingOutcome;
use stdClass;

/**
 * One `ops_outgoing_rollups` row as a mergeable Bucket. `count` is the timed calls (the ones
 * with a latency); the counters carry `calls`, `failed` and one per outcome.
 */
final readonly class OutgoingRow
{
    public const array COLUMNS = ['bucket', 'endpoint', 'method', 'status', 'outcome', 'calls', 'timed', 'sum_ms', 'max_ms', 'histogram'];

    public function __construct(
        public CarbonImmutable $at,
        public string $endpoint,
        public string $method,
        public int $status,
        public OutgoingOutcome $outcome,
        public Bucket $stats,
    ) {}

    public static function fromRecord(stdClass $record): self
    {
        $outcome = OutgoingOutcome::tryFrom((string) $record->outcome) ?? OutgoingOutcome::ConnectError;
        $calls = (int) $record->calls;
        $timed = (int) $record->timed;

        return new self(
            at: CarbonImmutable::parse((string) $record->bucket),
            endpoint: (string) $record->endpoint,
            method: (string) $record->method,
            status: (int) $record->status,
            outcome: $outcome,
            stats: new Bucket(
                $timed,
                (int) $record->sum_ms / 1000,
                $timed > 0 ? (int) $record->max_ms / 1000 : null,
                self::histogram($record->histogram ?? null),
                ['calls' => $calls, 'failed' => $outcome->isFailure() ? $calls : 0, $outcome->value => $calls],
            ),
        );
    }

    /** @return list<int> */
    private static function histogram(mixed $histogram): array
    {
        $bands = is_string($histogram) ? json_decode($histogram, true) : $histogram;

        return LatencyHistogram::normalize(is_array($bands) ? $bands : []);
    }
}
