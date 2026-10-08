<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Outgoing;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Phattarachai\OpsCore\Rollup\Bucket;
use Phattarachai\OpsLaravel\Enums\OutgoingOutcome;
use Phattarachai\OpsLaravel\Models\OutgoingRollup;
use Phattarachai\OpsLaravel\Ui\TimeRange;
use stdClass;

/**
 * Window queries over `ops_outgoing_rollups` and the stats shape the outgoing screens share.
 * Rows are merged in PHP (ops-core Bucket), so the SQL stays portable across drivers.
 */
final class OutgoingRows
{
    public const int BASELINE_DAYS = 7;

    /** @return Builder<OutgoingRollup> */
    public static function between(string $grain, CarbonImmutable $from, CarbonImmutable $to, ?string $endpoint = null): Builder
    {
        return OutgoingRollup::query()
            ->where('grain', $grain)
            ->when($endpoint !== null, fn (Builder $query) => $query->where('endpoint', $endpoint))
            ->where('bucket', '>=', $grain === 'hour' ? $from->startOfHour() : $from->startOfMinute())
            ->where('bucket', '<', $to);
    }

    /** @return Builder<OutgoingRollup> */
    public static function window(TimeRange $range, ?string $endpoint = null): Builder
    {
        return self::between($range->grain, $range->start, $range->end, $endpoint);
    }

    /** @return Builder<OutgoingRollup> */
    public static function baseline(?string $endpoint = null): Builder
    {
        return self::between('hour', CarbonImmutable::now()->subDays(self::BASELINE_DAYS), CarbonImmutable::now(), $endpoint);
    }

    /**
     * @param  Builder<OutgoingRollup>  $query
     * @return iterable<OutgoingRow>
     */
    public static function rows(Builder $query): iterable
    {
        foreach ($query->toBase()->select(OutgoingRow::COLUMNS)->cursor() as $record) {
            if ($record instanceof stdClass) {
                yield OutgoingRow::fromRecord($record);
            }
        }
    }

    public static function exists(string $endpoint): bool
    {
        return OutgoingRollup::query()->whereIn('grain', ['minute', 'hour'])->where('endpoint', $endpoint)->exists();
    }

    public static function latestBucket(): ?CarbonImmutable
    {
        $latest = OutgoingRollup::query()->where('grain', 'minute')->max('bucket');

        return $latest === null ? null : CarbonImmutable::parse((string) $latest);
    }

    public static function merge(?Bucket $carry, Bucket $stats): Bucket
    {
        return $carry === null ? $stats : $carry->merge($stats);
    }

    /** @return array<string, int> outcome => calls */
    public static function outcomeCounts(Bucket $bucket): array
    {
        $counts = [];

        foreach (OutgoingOutcome::cases() as $outcome) {
            $counts[$outcome->value] = $bucket->counter($outcome->value);
        }

        return $counts;
    }

    /**
     * @return array{calls: int, failed: int, p50: ?float, p95: ?float, max: ?float}
     */
    public static function stats(Bucket $bucket): array
    {
        return [
            'calls' => $bucket->counter('calls'),
            'failed' => $bucket->counter('failed'),
            'p50' => $bucket->count > 0 ? $bucket->p50() : null,
            'p95' => $bucket->count > 0 ? $bucket->p95() : null,
            'max' => $bucket->count > 0 ? $bucket->maxSeconds : null,
        ];
    }
}
