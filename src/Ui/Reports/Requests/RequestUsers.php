<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Requests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Phattarachai\OpsLaravel\Models\UserRequestRollup;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\TimeFilter;
use Phattarachai\OpsLaravel\Ui\TimeRange;

/** User names (through Ops::resolveUsers), the segment filter and the `?user=` panel. */
final class RequestUsers
{
    private const int MAX_HOUR_BARS = 48;

    private const int USER_ROUTES = 30;

    /** @param  list<string>  $segments */
    public static function segmentFilter(Request $request, array $segments): ?string
    {
        $segment = $request->query('segment');

        return is_string($segment) && in_array($segment, $segments, true) ? $segment : null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function withNames(array $rows): array
    {
        $ids = array_map(fn (array $row): string => (string) $row['user_id'], $rows);
        $names = self::names(array_values(array_unique($ids)));

        return array_map(fn (array $row): array => [...$row, ...$names[(string) $row['user_id']]], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function selected(Request $request, ?string $route, TimeFilter $filter): ?array
    {
        $userId = $request->query('user');

        if (! is_string($userId) || $userId === '' || mb_strlen($userId) > 64) {
            return null;
        }

        $range = $filter->range();
        $hours = self::hours($userId, $route, $range);

        return [
            'id' => $userId,
            ...self::names([$userId])[$userId],
            'segment' => self::latestSegment($userId),
            'scope' => $route,
            'scope_uri' => $route === null ? null : RouteCatalog::uri($route),
            'peak_per_minute' => max([0, ...array_column($hours, 'peak')]),
            'hours' => $hours,
            'routes' => self::routes($userId, $range, $filter),
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array{name: string, detail: ?string, user_url: ?string}>
     */
    private static function names(array $ids): array
    {
        $resolved = Ops::resolveUsers($ids);
        $names = [];

        foreach ($ids as $id) {
            $names[$id] = [
                'name' => (string) ($resolved[$id]['name'] ?? "#{$id}"),
                'detail' => $resolved[$id]['detail'] ?? null,
                'user_url' => $resolved[$id]['url'] ?? null,
            ];
        }

        return $names;
    }

    /**
     * @return list<array{label: string, value: int, peak: int, s429: int}>
     */
    private static function hours(string $userId, ?string $route, TimeRange $range): array
    {
        return OpsCache::remember('requests:user-hours:'.md5("{$userId}|{$route}|{$range->key}"), function () use ($userId, $route, $range): array {
            $hourRange = self::hourRange($range);
            $grid = new BarGrid($hourRange->firstBarStart(), $hourRange->step, $hourRange->bars);
            $bars = array_map(fn (int $index): array => ['label' => $hourRange->barLabel($index), 'value' => 0, 'peak' => 0, 's429' => 0], range(0, $hourRange->bars - 1));

            $rows = RequestRows::userWindow($range)
                ->where('user_id', $userId)
                ->when($route, fn (Builder $query, string $route) => $query->where('route', $route))
                ->get(['bucket', 'requests', 'peak_per_minute', 'status_429']);

            foreach ($rows as $row) {
                $index = $grid->index($row->bucket);

                if ($index !== null) {
                    $bars[$index]['value'] += $row->requests;
                    $bars[$index]['peak'] = max($bars[$index]['peak'], $row->peak_per_minute);
                    $bars[$index]['s429'] += $row->status_429;
                }
            }

            return $bars;
        });
    }

    private static function hourRange(TimeRange $range): TimeRange
    {
        $step = max(60, $range->step);
        $bars = (int) min(self::MAX_HOUR_BARS, max(1, ceil(abs($range->start->diffInMinutes($range->end)) / $step)));

        return new TimeRange($range->start, $range->end, 'hour', $step, $bars, $range->label, $range->key);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function routes(string $userId, TimeRange $range, TimeFilter $filter): array
    {
        $routes = OpsCache::remember('requests:user-routes:'.md5("{$userId}|{$range->key}"), fn (): array => RequestRows::userWindow($range)
            ->where('user_id', $userId)
            ->toBase()
            ->selectRaw('route, sum(requests) AS requests, sum(status_429) AS s429, sum(status_5xx) AS s5xx, max(peak_per_minute) AS peak_per_minute')
            ->groupBy('route')
            ->orderByDesc('requests')
            ->limit(self::USER_ROUTES)
            ->get()
            ->map(fn (object $row): array => [
                'route' => (string) $row->route,
                'requests' => (int) $row->requests,
                's429' => (int) $row->s429,
                's5xx' => (int) $row->s5xx,
                'peak_per_minute' => (int) $row->peak_per_minute,
            ])
            ->values()
            ->all());

        return array_map(fn (array $row): array => [
            ...$row,
            'uri' => RouteCatalog::uri($row['route']),
            'url' => RouteCatalog::url($row['route'], $filter, ['user' => $userId]),
        ], $routes);
    }

    private static function latestSegment(string $userId): ?string
    {
        $segment = UserRequestRollup::query()->where('user_id', $userId)->orderByDesc('bucket')->value('segment');

        return is_string($segment) && $segment !== '' ? $segment : null;
    }
}
