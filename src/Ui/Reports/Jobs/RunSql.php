<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Jobs;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsLaravel\Models\JobRun;

/**
 * SQL fragments for the run tables that read the same on pgsql, mysql and sqlite. Percentiles are
 * exact `percentile_cont` on pgsql; elsewhere each one selects latency-band counts and
 * {@see self::percentile()} interpolates them through ops-core's LatencyHistogram.
 */
final readonly class RunSql
{
    public const string FAILED = "status in ('failed', 'timed_out')";

    public const string TIMED = "status <> 'released'";

    public const string RAN = "status in ('success', 'failed')";

    public const string ORIGIN_GROUP = "case when origin = 'schedule' then 'schedule' when origin = 'web' then 'web' else 'other' end";

    public function __construct(private string $driver) {}

    public static function make(): self
    {
        return new self(new JobRun()->getConnection()->getDriverName());
    }

    public static function countWhere(string $condition): string
    {
        return "sum(case when {$condition} then 1 else 0 end)";
    }

    public static function maxWhere(string $column, string $condition): string
    {
        return "max(case when {$condition} then {$column} end)";
    }

    public static function avgWhere(string $column, string $condition): string
    {
        return "avg(case when {$condition} then {$column} end)";
    }

    /** Index of the `$stepSeconds`-wide slot a timestamp column falls in, counted from `$origin`. */
    public function slot(string $column, CarbonInterface $origin, int $stepSeconds): string
    {
        $offset = self::naiveEpoch($origin);

        return match ($this->driver) {
            'sqlite' => "((cast(strftime('%s', {$column}) as integer) - {$offset}) / {$stepSeconds})",
            'pgsql' => "floor((extract(epoch from {$column}) - {$offset}) / {$stepSeconds})",
            'sqlsrv' => "((datediff_big(second, '1970-01-01', {$column}) - {$offset}) / {$stepSeconds})",
            default => "floor((timestampdiff(second, '1970-01-01 00:00:00', {$column}) - {$offset}) / {$stepSeconds})",
        };
    }

    /** The latency band (0‥12, ops-core LatencyHistogram) of a millisecond column. */
    public static function band(string $column): string
    {
        $cases = array_map(
            fn (float $bound, int $index): string => 'when '.$column.' < '.self::ms($bound)." then {$index}",
            LatencyHistogram::BOUNDS,
            array_keys(LatencyHistogram::BOUNDS),
        );

        return 'case '.implode(' ', $cases).' else '.(LatencyHistogram::BIN_COUNT - 1).' end';
    }

    /**
     * Select fragments for the given quantiles of `$column` (ms) over the rows matching `$condition`.
     *
     * @param  list<float>  $quantiles
     */
    public function percentiles(string $alias, string $column, string $condition, array $quantiles): string
    {
        if ($this->driver === 'pgsql') {
            return implode(', ', array_map(
                fn (float $quantile): string => "percentile_cont({$quantile}) within group (order by {$column}) filter (where {$condition}) as {$alias}_q".self::quantileKey($quantile),
                $quantiles,
            ));
        }

        $bands = array_map(
            fn (int $index): string => self::countWhere("({$condition}) and {$column} is not null and ".self::bandRange($column, $index))." as {$alias}_b{$index}",
            range(0, LatencyHistogram::BIN_COUNT - 1),
        );

        return implode(', ', [...$bands, self::maxWhere($column, $condition)." as {$alias}_max"]);
    }

    /** A percentile selected by {@see self::percentiles()}, in milliseconds. */
    public function percentile(?object $row, string $alias, float $quantile): ?float
    {
        if ($row === null) {
            return null;
        }

        if ($this->driver === 'pgsql') {
            $value = $row->{$alias.'_q'.self::quantileKey($quantile)} ?? null;

            return $value === null ? null : (float) $value;
        }

        $bins = array_map(fn (int $index): int => (int) ($row->{"{$alias}_b{$index}"} ?? 0), range(0, LatencyHistogram::BIN_COUNT - 1));
        $max = $row->{"{$alias}_max"} ?? null;
        $seconds = LatencyHistogram::percentile($bins, $quantile, $max === null ? null : (float) $max / 1000);

        return $seconds === null ? null : min($seconds * 1000, $max === null ? INF : (float) $max);
    }

    private static function bandRange(string $column, int $index): string
    {
        $lower = $index === 0 ? null : LatencyHistogram::BOUNDS[$index - 1];
        $upper = LatencyHistogram::BOUNDS[$index] ?? null;

        return implode(' and ', array_filter([
            $lower === null ? null : "{$column} >= ".self::ms($lower),
            $upper === null ? null : "{$column} < ".self::ms($upper),
        ]));
    }

    private static function ms(float $seconds): int
    {
        return (int) round($seconds * 1000);
    }

    private static function quantileKey(float $quantile): string
    {
        return (string) (int) round($quantile * 100);
    }

    /** Seconds since the epoch of the wall-clock time, read as UTC — the way the driver reads a naive timestamp column. */
    private static function naiveEpoch(CarbonInterface $at): int
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $at->format('Y-m-d H:i:s'), 'UTC')->getTimestamp();
    }
}
