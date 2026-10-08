<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Requests;

use Carbon\CarbonImmutable;
use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsCore\Rollup\Bucket;
use Phattarachai\OpsLaravel\Models\RequestRollup;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\TimeRange;

/** One route over the window: status bars, the segment split and the duration histogram, in one pass. */
final class RouteDetailReport
{
    /**
     * @return array{buckets: list<array<string, mixed>>, segments: list<array<string, mixed>>, histogram: list<array{label: string, count: int, failed: int}>}
     */
    public static function charts(string $route, TimeRange $range): array
    {
        return OpsCache::remember('requests:detail:'.md5("{$route}|{$range->key}"), fn (): array => self::buildCharts($route, $range));
    }

    /**
     * Today so far (closed hours) against the same hours of the same weekday last week.
     *
     * @return array{today: int, last_week: int, delta: ?int}
     */
    public static function sameTimeLastWeek(string $route): array
    {
        return OpsCache::remember('requests:week:'.md5($route), function () use ($route): array {
            $today = self::closedHoursToday($route, weeksBack: 0);
            $lastWeek = self::closedHoursToday($route, weeksBack: 1);

            return ['today' => $today, 'last_week' => $lastWeek, 'delta' => RequestRows::delta($today, $lastWeek)];
        });
    }

    /**
     * @return array{buckets: list<array<string, mixed>>, segments: list<array<string, mixed>>, histogram: list<array{label: string, count: int, failed: int}>}
     */
    private static function buildCharts(string $route, TimeRange $range): array
    {
        $grid = new BarGrid($range->firstBarStart(), $range->step, $range->bars);
        $bars = array_fill(0, $range->bars, new Bucket);
        $segments = [];
        $total = new Bucket;

        foreach (RequestRows::rows(RequestRows::window($range)->where('route', $route)) as $row) {
            $total = $total->merge($row->stats);
            $segments[$row->segment] = ($segments[$row->segment] ?? new Bucket)->merge($row->stats);
            $index = $grid->index($row->at);

            if ($index !== null) {
                $bars[$index] = $bars[$index]->merge($row->stats);
            }
        }

        return [
            'buckets' => self::bars($bars, $range),
            'segments' => self::segmentRows($segments),
            'histogram' => self::histogram($total),
        ];
    }

    /**
     * @param  list<Bucket>  $bars
     * @return list<array<string, mixed>>
     */
    private static function bars(array $bars, TimeRange $range): array
    {
        return array_map(function (Bucket $bar, int $index) use ($range): array {
            $stats = RequestRows::stats($bar);

            return [
                'label' => $range->barLabel($index),
                'ok' => $stats['ok'],
                's4xx' => $stats['s4xx'],
                's429' => $stats['s429'],
                's5xx' => $stats['s5xx'],
                'failed' => $stats['s5xx'],
                'p50' => $stats['p50'],
                'p95' => $stats['p95'],
                'max' => $stats['max'],
            ];
        }, $bars, array_keys($bars));
    }

    /**
     * @param  array<string, Bucket>  $segments
     * @return list<array<string, mixed>>
     */
    private static function segmentRows(array $segments): array
    {
        $rows = [];

        foreach ($segments as $segment => $stats) {
            $rows[] = ['segment' => (string) $segment, ...RequestRows::stats($stats)];
        }

        usort($rows, fn (array $a, array $b): int => $b['requests'] <=> $a['requests']);

        return $rows;
    }

    /** @return list<array{label: string, count: int, failed: int}> */
    private static function histogram(Bucket $total): array
    {
        $bands = LatencyHistogram::normalize($total->histogram);
        $bounds = LatencyHistogram::BOUNDS;

        return array_map(fn (int $band): array => [
            'label' => $band < count($bounds) ? '<'.Format::duration($bounds[$band]) : '≥'.Format::duration($bounds[count($bounds) - 1]),
            'count' => $bands[$band],
            'failed' => 0,
        ], range(0, LatencyHistogram::BIN_COUNT - 1));
    }

    private static function closedHoursToday(string $route, int $weeksBack): int
    {
        $now = CarbonImmutable::now()->subWeeks($weeksBack);

        return (int) RequestRollup::query()
            ->where('grain', 'hour')
            ->where('route', $route)
            ->where('bucket', '>=', $now->startOfDay())
            ->where('bucket', '<', $now->startOfHour())
            ->sum('requests');
    }
}
