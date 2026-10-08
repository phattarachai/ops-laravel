<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Requests;

use Carbon\CarbonImmutable;
use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsCore\Rollup\Bucket;
use stdClass;

/**
 * One `ops_http_rollups` row as a mergeable Bucket whose counters are the status keys
 * (`ok`, `s4xx`, `s429`, `s5xx`) the dashboard shows.
 */
final readonly class RequestRow
{
    public const array STATUS_KEYS = ['ok', 's4xx', 's429', 's5xx'];

    public function __construct(
        public CarbonImmutable $at,
        public string $route,
        public string $method,
        public string $segment,
        public string $statusKey,
        public Bucket $stats,
    ) {}

    public static function fromRecord(stdClass $record): self
    {
        $requests = (int) $record->requests;
        $statusKey = self::statusKey((int) $record->status);

        return new self(
            at: CarbonImmutable::parse((string) $record->bucket),
            route: (string) $record->route,
            method: (string) $record->method,
            segment: (string) $record->segment,
            statusKey: $statusKey,
            stats: new Bucket($requests, (int) $record->sum_ms / 1000, (int) $record->max_ms / 1000, self::histogram($record->histogram), [$statusKey => $requests]),
        );
    }

    public static function statusKey(int $status): string
    {
        return match (true) {
            $status === 429 => 's429',
            $status >= 500 => 's5xx',
            $status >= 400 => 's4xx',
            default => 'ok',
        };
    }

    /** @return list<int> */
    private static function histogram(mixed $histogram): array
    {
        $bands = is_string($histogram) ? json_decode($histogram, true) : $histogram;

        return LatencyHistogram::normalize(is_array($bands) ? $bands : []);
    }
}
