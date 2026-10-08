<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Rollup;

use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsCore\Rollup\Bucket;

final class HistogramRows
{
    /**
     * @param  array<string, int>  $metrics  `b{n}` band counts
     * @return list<int>
     */
    public static function fromMetrics(array $metrics): array
    {
        return array_map(static fn (int $band): int => $metrics["b{$band}"] ?? 0, range(0, LatencyHistogram::BIN_COUNT - 1));
    }

    /**
     * @param  list<int>  $histogram
     */
    public static function bucket(int $count, int $sumMs, int $maxMs, array $histogram): Bucket
    {
        return new Bucket($count, $sumMs / 1000, $maxMs / 1000, LatencyHistogram::normalize($histogram));
    }

    /** @return array{p50_ms: int|null, p95_ms: int|null} */
    public static function percentiles(Bucket $bucket): array
    {
        return [
            'p50_ms' => self::milliseconds($bucket->p50()),
            'p95_ms' => self::milliseconds($bucket->p95()),
        ];
    }

    private static function milliseconds(?float $seconds): ?int
    {
        return $seconds === null ? null : (int) round($seconds * 1000);
    }
}
