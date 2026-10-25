<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Rollup;

use Carbon\CarbonImmutable;
use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsLaravel\Buffer\MinuteBuffer;
use Phattarachai\OpsLaravel\Buffer\MinuteData;
use Phattarachai\OpsLaravel\Models\RequestRollup;
use Phattarachai\OpsLaravel\Models\SignalRollup;
use Phattarachai\OpsLaravel\Models\UserRequestRollup;
use Phattarachai\OpsLaravel\Recorders\RequestRecorder;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use Phattarachai\OpsLaravel\Signals\BuiltInSignals;

/**
 * Minute rows per route × method × status × segment, user rows added into their hour, and the
 * busy user × route minutes as `http.user_route` signal rows for the rate rule.
 */
class RequestStreamRollup extends StreamRollup
{
    private const array DIMENSIONS = ['route', 'method', 'status', 'segment'];

    public function __construct(MinuteBuffer $buffer, protected OpsSettings $settings)
    {
        parent::__construct($buffer);
    }

    public function stream(): string
    {
        return RequestRecorder::STREAM;
    }

    public function foldHours(CarbonImmutable $fromHour, CarbonImmutable $toHour): int
    {
        $groups = [];

        RequestRollup::query()
            ->where('grain', 'minute')
            ->where('bucket', '>=', $fromHour)
            ->where('bucket', '<', $toHour->addHour())
            ->orderBy('id')
            ->each(function (RequestRollup $row) use (&$groups): void {
                $key = $row->bucket->startOfHour()->format('Y-m-d H:i:s').'|'.MinuteData::dims([$row->route, $row->method, $row->status, $row->segment]);
                $groups[$key] ??= ['bucket' => $row->bucket->startOfHour(), 'route' => $row->route, 'method' => $row->method, 'status' => $row->status, 'segment' => $row->segment, 'requests' => 0, 'sum_ms' => 0, 'max_ms' => 0, 'histogram' => LatencyHistogram::empty()];
                $groups[$key]['requests'] += $row->requests;
                $groups[$key]['sum_ms'] += $row->sum_ms;
                $groups[$key]['max_ms'] = max($groups[$key]['max_ms'], $row->max_ms);
                $groups[$key]['histogram'] = LatencyHistogram::merge($groups[$key]['histogram'], $row->histogram ?? []);
            });

        $rows = array_map(fn (array $group): array => $this->row('hour', $group), array_values($groups));

        foreach (array_chunk($rows, 500) as $chunk) {
            RequestRollup::query()->upsert($chunk, ['grain', 'bucket', ...self::DIMENSIONS], ['requests', 'sum_ms', 'max_ms', 'p50_ms', 'p95_ms', 'histogram', 'updated_at']);
        }

        return count($rows);
    }

    protected function read(CarbonImmutable $minute): MinuteData
    {
        $requests = parent::read($minute);
        $users = $this->buffer->read(RequestRecorder::USER_STREAM, $minute);

        return new MinuteData([...$requests->fields, ...$this->prefixed($users->fields)], $requests->maxima);
    }

    protected function forget(CarbonImmutable $minute): void
    {
        parent::forget($minute);
        $this->buffer->forget(RequestRecorder::USER_STREAM, $minute);
    }

    protected function write(CarbonImmutable $minute, MinuteData $data): void
    {
        [$routeGroups, $userGroups] = $this->partition($data);

        $this->insertMinute($minute, $routeGroups, $data);
        $this->accumulateUsers($minute, $userGroups);
    }

    /**
     * @param  array<string, array<string, int>>  $groups
     */
    protected function insertMinute(CarbonImmutable $minute, array $groups, MinuteData $data): void
    {
        $rows = [];

        foreach ($groups as $dims => $metrics) {
            [$route, $method, $status, $segment] = array_pad(MinuteData::split($dims), 4, '');

            $rows[] = $this->row('minute', [
                'bucket' => $minute,
                'route' => $route,
                'method' => $method,
                'status' => (int) $status,
                'segment' => $segment,
                'requests' => $metrics['c'] ?? 0,
                'sum_ms' => $metrics['ms'] ?? 0,
                'max_ms' => (int) $data->max($dims),
                'histogram' => HistogramRows::fromMetrics($metrics),
            ]);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            RequestRollup::query()->insert($chunk);
        }
    }

    /**
     * @param  array<string, array<string, int>>  $groups
     */
    protected function accumulateUsers(CarbonImmutable $minute, array $groups): void
    {
        $hour = $minute->startOfHour();
        $minuteRows = $this->userRows($groups);

        if ($minuteRows === []) {
            return;
        }

        $existing = UserRequestRollup::query()
            ->where('bucket', $hour)
            ->whereIn('route', array_unique(array_column($minuteRows, 'route')))
            ->get()
            ->keyBy(fn (UserRequestRollup $row): string => "{$row->route}|{$row->user_id}");

        $now = now();
        $rows = array_map(function (array $row) use ($existing, $hour, $now): array {
            $current = $existing->get("{$row['route']}|{$row['user_id']}");

            return [
                ...$row,
                'bucket' => $hour,
                'requests' => $row['requests'] + ($current->requests ?? 0),
                'status_4xx' => $row['status_4xx'] + ($current->status_4xx ?? 0),
                'status_429' => $row['status_429'] + ($current->status_429 ?? 0),
                'status_5xx' => $row['status_5xx'] + ($current->status_5xx ?? 0),
                'sum_ms' => $row['sum_ms'] + ($current->sum_ms ?? 0),
                'peak_per_minute' => max($row['requests'], $current->peak_per_minute ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $minuteRows);

        foreach (array_chunk($rows, 500) as $chunk) {
            UserRequestRollup::query()->upsert($chunk, ['bucket', 'route', 'user_id'], ['segment', 'requests', 'status_4xx', 'status_429', 'status_5xx', 'sum_ms', 'peak_per_minute', 'updated_at']);
        }

        $this->recordBusyUsers($minute, $minuteRows);
    }

    /**
     * @param  list<array{route: string, user_id: string, segment: string, requests: int}>  $rows
     */
    protected function recordBusyUsers(CarbonImmutable $minute, array $rows): void
    {
        $floor = max(1, $this->settings->int('signals.user_route_floor', 30));
        $now = now();

        $signals = array_values(array_map(fn (array $row): array => [
            'bucket' => $minute,
            'signal' => BuiltInSignals::USER_ROUTE,
            'key' => mb_substr("user:{$row['user_id']}|{$row['route']}", 0, 255),
            'count' => $row['requests'],
            'created_at' => $now,
            'updated_at' => $now,
        ], array_filter($rows, fn (array $row): bool => $row['requests'] >= $floor)));

        foreach (array_chunk($signals, 500) as $chunk) {
            SignalRollup::query()->insert($chunk);
        }
    }

    /**
     * Rows for the same route × user merge, the last segment winning, since two rows with one
     * conflict key would fail the upsert.
     *
     * @param  array<string, array<string, int>>  $groups
     * @return list<array{route: string, user_id: string, segment: string, requests: int, status_4xx: int, status_429: int, status_5xx: int, sum_ms: int}>
     */
    protected function userRows(array $groups): array
    {
        $rows = [];

        foreach ($groups as $dims => $metrics) {
            [$route, $userId, $segment] = array_pad(MinuteData::split($dims), 3, '');
            $key = "{$route}|{$userId}";
            $row = $rows[$key] ?? ['route' => $route, 'user_id' => $userId, 'segment' => $segment, 'requests' => 0, 'status_4xx' => 0, 'status_429' => 0, 'status_5xx' => 0, 'sum_ms' => 0];

            $rows[$key] = [
                ...$row,
                'segment' => $segment,
                'requests' => $row['requests'] + ($metrics['c'] ?? 0),
                'status_4xx' => $row['status_4xx'] + ($metrics['4xx'] ?? 0),
                'status_429' => $row['status_429'] + ($metrics['429'] ?? 0),
                'status_5xx' => $row['status_5xx'] + ($metrics['5xx'] ?? 0),
                'sum_ms' => $row['sum_ms'] + ($metrics['ms'] ?? 0),
            ];
        }

        return array_values($rows);
    }

    /**
     * @param  array{bucket: CarbonImmutable, route: string, method: string, status: int, segment: string, requests: int, sum_ms: int, max_ms: int, histogram: list<int>}  $group
     * @return array<model-property<RequestRollup>, mixed>
     */
    protected function row(string $grain, array $group): array
    {
        $bucket = HistogramRows::bucket($group['requests'], $group['sum_ms'], $group['max_ms'], $group['histogram']);

        return [
            ...$group,
            ...HistogramRows::percentiles($bucket),
            'grain' => $grain,
            'histogram' => json_encode($group['histogram']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<string, int>  $fields
     * @return array<string, int>
     */
    private function prefixed(array $fields): array
    {
        $prefixed = [];

        foreach ($fields as $field => $value) {
            $prefixed["u:{$field}"] = $value;
        }

        return $prefixed;
    }

    /** @return array{0: array<string, array<string, int>>, 1: array<string, array<string, int>>} */
    private function partition(MinuteData $data): array
    {
        $routes = [];
        $users = [];

        foreach ($data->grouped() as $dims => $metrics) {
            str_starts_with($dims, 'u:') ? $users[substr($dims, 2)] = $metrics : $routes[$dims] = $metrics;
        }

        return [$routes, $users];
    }
}
