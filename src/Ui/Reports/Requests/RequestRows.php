<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Requests;

use Illuminate\Database\Eloquent\Builder;
use Phattarachai\OpsCore\Rollup\Bucket;
use Phattarachai\OpsLaravel\Models\RequestRollup;
use Phattarachai\OpsLaravel\Models\UserRequestRollup;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\TimeRange;
use stdClass;

/**
 * Window queries over the request rollups and the stats shape every request report shares.
 * Rows are merged in PHP (ops-core Bucket), so the SQL stays portable across drivers.
 */
final class RequestRows
{
    private const array COLUMNS = ['bucket', 'route', 'method', 'status', 'segment', 'requests', 'sum_ms', 'max_ms', 'histogram'];

    /** @return Builder<RequestRollup> */
    public static function window(TimeRange $range, int $weeksBack = 0): Builder
    {
        $start = $range->isMinuteGrain() ? $range->start->startOfMinute() : $range->start->startOfHour();

        return RequestRollup::query()
            ->where('grain', $range->grain)
            ->where('bucket', '>=', $start->subWeeks($weeksBack))
            ->where('bucket', '<', $range->end->subWeeks($weeksBack));
    }

    /** @return Builder<UserRequestRollup> */
    public static function userWindow(TimeRange $range): Builder
    {
        return UserRequestRollup::query()
            ->where('bucket', '>=', $range->start->startOfHour())
            ->where('bucket', '<', $range->end);
    }

    /**
     * @param  Builder<RequestRollup>  $query
     * @return iterable<RequestRow>
     */
    public static function rows(Builder $query): iterable
    {
        foreach ($query->toBase()->select(self::COLUMNS)->cursor() as $record) {
            if ($record instanceof stdClass) {
                yield RequestRow::fromRecord($record);
            }
        }
    }

    /**
     * @return array{requests: int, ok: int, s4xx: int, s429: int, s5xx: int, p50: ?float, p95: ?float, max: ?float, p50_label: string, p95_label: string, avg_label: string}
     */
    public static function stats(Bucket $bucket): array
    {
        $p50 = $bucket->count > 0 ? $bucket->p50() : null;
        $p95 = $bucket->count > 0 ? $bucket->p95() : null;

        return [
            'requests' => $bucket->count,
            'ok' => $bucket->counter('ok'),
            's4xx' => $bucket->counter('s4xx'),
            's429' => $bucket->counter('s429'),
            's5xx' => $bucket->counter('s5xx'),
            'p50' => $p50,
            'p95' => $p95,
            'max' => $bucket->count > 0 ? $bucket->maxSeconds : null,
            'p50_label' => Format::duration($p50),
            'p95_label' => Format::duration($p95),
            'avg_label' => Format::duration($bucket->averageSeconds()),
        ];
    }

    /** Percent change against an earlier count; null when there is nothing to compare with. */
    public static function delta(int $now, int $before): ?int
    {
        return $before === 0 ? null : (int) round(($now - $before) / $before * 100);
    }
}
