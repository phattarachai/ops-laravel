<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Phattarachai\OpsCore\Rollup\Bucket;
use Phattarachai\OpsLaravel\Models\RequestRollup;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Models\UserRequestRollup;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\TimeRange;

/**
 * The Incoming board: summary and per-route rows from one pass over the window, plus the
 * user-rollup reads (top users, hot users, segments).
 */
final class RequestsReport
{
    public const string FLOOD_RULE = 'user-route-flood';

    public const int DEFAULT_THRESHOLD = 120;

    private const int TOP_USERS = 25;

    private const int HOT_USERS = 15;

    private const int SPARK_BARS = 24;

    /**
     * @return array{summary: array<string, mixed>, routes: list<array<string, mixed>>}
     */
    public static function board(TimeRange $range): array
    {
        return OpsCache::remember("requests:board:{$range->key}", fn (): array => self::buildBoard($range));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function topUsers(TimeRange $range, ?string $route = null, ?string $segment = null): array
    {
        return OpsCache::remember('requests:users:'.md5("{$range->key}|{$route}|{$segment}"), fn (): array => RequestUsers::withNames(
            RequestRows::userWindow($range)
                ->when($route, fn (Builder $query, string $route) => $query->where('route', $route))
                ->when($segment, fn (Builder $query, string $segment) => $query->where('segment', $segment))
                ->toBase()
                ->selectRaw(<<<'SQL'
                    user_id, max(segment) AS segment, sum(requests) AS requests, sum(status_4xx) AS s4xx,
                    sum(status_429) AS s429, sum(status_5xx) AS s5xx, sum(sum_ms) AS sum_ms,
                    max(peak_per_minute) AS peak_per_minute, count(DISTINCT route) AS routes
                    SQL)
                ->groupBy('user_id')
                ->orderByDesc('requests')
                ->limit(self::TOP_USERS)
                ->get()
                ->map(fn (object $row): array => [
                    'user_id' => (string) $row->user_id,
                    'segment' => (string) $row->segment,
                    'requests' => (int) $row->requests,
                    's4xx' => (int) $row->s4xx,
                    's429' => (int) $row->s429,
                    's5xx' => (int) $row->s5xx,
                    'peak_per_minute' => (int) $row->peak_per_minute,
                    'avg_label' => Format::duration((int) $row->sum_ms / max(1, (int) $row->requests) / 1000),
                    'routes' => (int) $row->routes,
                ])
                ->values()
                ->all(),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function hotUsers(TimeRange $range, int $threshold): array
    {
        if ($threshold <= 0) {
            return [];
        }

        return OpsCache::remember("requests:hot:{$range->key}:{$threshold}", fn (): array => RequestUsers::withNames(
            RequestRows::userWindow($range)
                ->where('peak_per_minute', '>=', $threshold)
                ->orderByDesc('peak_per_minute')
                ->limit(self::HOT_USERS)
                ->get()
                ->map(fn (UserRequestRollup $row): array => [
                    'route' => $row->route,
                    'token' => RouteCatalog::token($row->route),
                    'uri' => RouteCatalog::uri($row->route),
                    'user_id' => $row->user_id,
                    'segment' => $row->segment,
                    'requests' => $row->requests,
                    's429' => $row->status_429,
                    'peak_per_minute' => $row->peak_per_minute,
                    'at' => $row->bucket->toIso8601String(),
                    'period_label' => Format::date($row->bucket).' – '.Format::date($row->bucket->addHour(), 'H:i'),
                ])
                ->values()
                ->all(),
        ));
    }

    /** @return list<string> */
    public static function segments(TimeRange $range): array
    {
        return OpsCache::remember("requests:segments:{$range->key}", fn (): array => RequestRows::userWindow($range)
            ->where('segment', '<>', '')
            ->distinct()
            ->orderBy('segment')
            ->pluck('segment')
            ->map(fn (mixed $segment): string => (string) $segment)
            ->values()
            ->all());
    }

    public static function threshold(): int
    {
        $threshold = Rule::query()->where('slug', self::FLOOD_RULE)->value('threshold');

        return $threshold === null ? self::DEFAULT_THRESHOLD : (int) $threshold;
    }

    public static function dataUpdatedAt(): ?CarbonImmutable
    {
        $at = RequestRollup::query()->where('grain', 'minute')->max('bucket');

        return $at === null ? null : CarbonImmutable::parse((string) $at);
    }

    /**
     * @return array{summary: array<string, mixed>, routes: list<array<string, mixed>>}
     */
    private static function buildBoard(TimeRange $range): array
    {
        $grid = self::sparkGrid($range);
        $total = new Bucket;
        $routes = [];

        foreach (RequestRows::rows(RequestRows::window($range)) as $row) {
            $total = $total->merge($row->stats);
            $routes[$row->route] = self::accumulate($routes[$row->route] ?? self::emptyRoute($grid), $row, $grid);
        }

        $lastWeek = self::lastWeekByRoute($range);

        return [
            'summary' => self::summary($range, $total, array_sum($lastWeek)),
            'routes' => self::routeRows($routes, $lastWeek),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(TimeRange $range, Bucket $total, int $lastWeek): array
    {
        return [
            ...RequestRows::stats($total),
            'users' => RequestRows::userWindow($range)->distinct()->count('user_id'),
            'last_week' => $lastWeek,
            'delta' => RequestRows::delta($total->count, $lastWeek),
        ];
    }

    /**
     * @param  array<string, array{stats: Bucket, methods: array<string, true>, spark: list<array<string, int>>}>  $routes
     * @param  array<string, int>  $lastWeek
     * @return list<array<string, mixed>>
     */
    private static function routeRows(array $routes, array $lastWeek): array
    {
        $rows = [];

        foreach ($routes as $route => $data) {
            $route = (string) $route;
            $methods = array_keys($data['methods']);
            sort($methods);

            $rows[] = [
                'route' => $route,
                'token' => RouteCatalog::token($route),
                'methods' => implode('/', $methods),
                ...RouteCatalog::describe($route),
                ...RequestRows::stats($data['stats']),
                'last_week' => $lastWeek[$route] ?? 0,
                'delta' => RequestRows::delta($data['stats']->count, $lastWeek[$route] ?? 0),
                'spark' => $data['spark'],
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['requests'] <=> $a['requests']);

        return $rows;
    }

    /**
     * @param  array{stats: Bucket, methods: array<string, true>, spark: list<array<string, int>>}  $route
     * @return array{stats: Bucket, methods: array<string, true>, spark: list<array<string, int>>}
     */
    private static function accumulate(array $route, RequestRow $row, BarGrid $grid): array
    {
        $route['stats'] = $route['stats']->merge($row->stats);
        $route['methods'][$row->method] = true;
        $index = $grid->index($row->at);

        if ($index !== null) {
            $route['spark'][$index][$row->statusKey] += $row->stats->count;
        }

        return $route;
    }

    /**
     * @return array{stats: Bucket, methods: array<string, true>, spark: list<array<string, int>>}
     */
    private static function emptyRoute(BarGrid $grid): array
    {
        return [
            'stats' => new Bucket,
            'methods' => [],
            'spark' => array_fill(0, $grid->bars, array_fill_keys(RequestRow::STATUS_KEYS, 0)),
        ];
    }

    private static function sparkGrid(TimeRange $range): BarGrid
    {
        $origin = $range->isMinuteGrain() ? $range->start->startOfMinute() : $range->start->startOfHour();
        $bars = min(self::SPARK_BARS, $range->bars);
        $span = abs($origin->diffInMinutes($range->end));

        return new BarGrid($origin, max($range->step, (int) ceil($span / $bars)), $bars);
    }

    /** @return array<string, int> */
    private static function lastWeekByRoute(TimeRange $range): array
    {
        return RequestRows::window($range, weeksBack: 1)
            ->toBase()
            ->selectRaw('route, sum(requests) AS requests')
            ->groupBy('route')
            ->pluck('requests', 'route')
            ->map(fn (mixed $requests): int => (int) $requests)
            ->all();
    }
}
