<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\System;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Phattarachai\OpsLaravel\Models\CacheRollup;
use Phattarachai\OpsLaravel\Models\Deploy;
use Phattarachai\OpsLaravel\Models\QueueSample;
use Phattarachai\OpsLaravel\Models\SignalRollup;
use Phattarachai\OpsLaravel\Models\SignalValue;
use Phattarachai\OpsLaravel\Models\SlowQuery;
use Phattarachai\OpsLaravel\Models\SystemSample;
use Phattarachai\OpsLaravel\Signals\BuiltInSignals;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\TimeRange;

/**
 * The Pulse-parity cards: slow queries, cache hit rate, queue depth over time, servers,
 * exceptions and deploy markers, all for the selected window.
 */
final readonly class SystemReport
{
    private const int LIMIT = 20;

    public function __construct(private TimeRange $range) {}

    /** @return array<string, mixed> */
    public function props(): array
    {
        return OpsCache::remember("system:{$this->range->key}", fn (): array => [
            'queries' => $this->slowQueries(),
            'cache' => $this->cache(),
            'queues' => $this->queues(),
            'servers' => $this->servers(),
            'exceptions' => $this->exceptions(),
            'deploys' => $this->deploys(),
            'bars' => array_map(fn (int $index): string => $this->range->barLabel($index), range(0, $this->range->bars - 1)),
        ]);
    }

    public function dataUpdatedAt(): ?CarbonImmutable
    {
        $latest = QueueSample::query()->max('bucket') ?? SystemSample::query()->max('bucket');

        return $latest === null ? null : CarbonImmutable::parse((string) $latest);
    }

    /** @return list<array<string, mixed>> */
    private function slowQueries(): array
    {
        return SlowQuery::query()
            ->where('bucket', '>=', $this->range->start->startOfHour())
            ->where('bucket', '<', $this->range->end)
            ->get()
            ->groupBy('hash')
            ->map(fn (Collection $rows): array => [
                'hash' => $rows->first()->hash,
                'sql' => $rows->first()->sql,
                'location' => $rows->first()->location,
                'connection' => $rows->first()->connection,
                'count' => (int) $rows->sum('count'),
                'total_ms' => (int) $rows->sum('sum_ms'),
                'avg_label' => Format::duration($rows->sum('sum_ms') / max(1, $rows->sum('count')) / 1000),
                'max_label' => Format::duration($rows->max('max_ms') / 1000),
            ])
            ->sortByDesc('total_ms')
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /** @return array{hits: int, misses: int, rate: string, keys: list<array<string, mixed>>} */
    private function cache(): array
    {
        $keys = CacheRollup::query()
            ->where('bucket', '>=', $this->range->start->startOfHour())
            ->where('bucket', '<', $this->range->end)
            ->get()
            ->groupBy('key')
            ->map(fn (Collection $rows, string $key): array => [
                'key' => $key,
                'hits' => (int) $rows->sum('hits'),
                'misses' => (int) $rows->sum('misses'),
            ])
            ->map(fn (array $row): array => $row + ['rate' => Format::percent($row['hits'], $row['hits'] + $row['misses'])])
            ->sortByDesc(fn (array $row): int => $row['hits'] + $row['misses'])
            ->take(self::LIMIT)
            ->values();

        $hits = (int) $keys->sum('hits');
        $misses = (int) $keys->sum('misses');

        return ['hits' => $hits, 'misses' => $misses, 'rate' => Format::percent($hits, $hits + $misses), 'keys' => $keys->all()];
    }

    /** @return list<array<string, mixed>> */
    private function queues(): array
    {
        return QueueSample::query()
            ->where('bucket', '>=', $this->range->start)
            ->where('bucket', '<', $this->range->end)
            ->orderBy('bucket')
            ->get()
            ->groupBy(fn (QueueSample $sample): string => "{$sample->connection}:{$sample->queue}")
            ->map(function (Collection $samples, string $name): array {
                $latest = $samples->last();

                return [
                    'name' => $name,
                    'size' => $latest->size,
                    'pending' => $latest->pending,
                    'delayed' => $latest->delayed,
                    'reserved' => $latest->reserved,
                    'oldest_wait' => $latest->oldest_wait_seconds === null ? '—' : Format::duration((float) $latest->oldest_wait_seconds),
                    'peak' => (int) $samples->max('size'),
                    'series' => $this->series($samples, fn (Collection $bar): int => (int) $bar->max('size')),
                    'wait_series' => $this->series($samples, fn (Collection $bar): int => (int) $bar->max('oldest_wait_seconds')),
                ];
            })
            ->sortByDesc('peak')
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function servers(): array
    {
        return SystemSample::query()
            ->where('bucket', '>=', $this->range->start)
            ->where('bucket', '<', $this->range->end)
            ->orderBy('bucket')
            ->get()
            ->groupBy('host')
            ->map(function (Collection $samples, string $host): array {
                $latest = $samples->last();

                return [
                    'host' => $host,
                    'load' => $latest->load_1,
                    'cpus' => $latest->cpus,
                    'memory' => $latest->memory_used_pct,
                    'disk' => $latest->disk_used_pct,
                    'memory_series' => $this->series($samples, fn (Collection $bar): ?float => $bar->max('memory_used_pct')),
                    'load_series' => $this->series($samples, fn (Collection $bar): ?float => $bar->max('load_1')),
                    'seen' => Format::date($latest->bucket, 'j M H:i'),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function exceptions(): array
    {
        $window = [$this->range->start->startOfMinute(), $this->range->end];
        $locations = SignalValue::query()
            ->where('signal', BuiltInSignals::EXCEPTION)
            ->where('field', 'location')
            ->whereBetween('bucket', $window)
            ->get()
            ->groupBy('key')
            ->map(fn (Collection $rows): array => $rows->pluck('value')->unique()->take(3)->values()->all());

        return SignalRollup::query()
            ->where('signal', BuiltInSignals::EXCEPTION)
            ->whereBetween('bucket', $window)
            ->get()
            ->groupBy('key')
            ->map(fn (Collection $rows, string $class): array => [
                'class' => $class,
                'count' => (int) $rows->sum('count'),
                'last' => Format::date($rows->max('bucket'), 'j M H:i'),
                'locations' => $locations->get($class, []),
                'series' => $this->series($rows, fn (Collection $bar): int => (int) $bar->sum('count')),
            ])
            ->sortByDesc('count')
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /** @return list<array{release: string, description: string|null, at: string|null, bar: int|null}> */
    private function deploys(): array
    {
        return Deploy::query()
            ->where('deployed_at', '>=', $this->range->start)
            ->where('deployed_at', '<', $this->range->end)
            ->latest('deployed_at')
            ->get()
            ->map(fn (Deploy $deploy): array => [
                'release' => $deploy->release,
                'description' => $deploy->description,
                'at' => Format::date($deploy->deployed_at, 'j M H:i'),
                'bar' => $this->barIndex($deploy->deployed_at),
            ])
            ->all();
    }

    /**
     * @template TModel of \Phattarachai\OpsLaravel\Models\OpsModel
     *
     * @param  Collection<int, TModel>  $rows
     * @param  callable(Collection<int, TModel>): (int|float|null)  $reduce
     * @return list<int|float|null>
     */
    private function series(Collection $rows, callable $reduce): array
    {
        $bars = array_fill(0, $this->range->bars, null);

        foreach ($rows->groupBy(fn ($row): ?int => $this->barIndex(CarbonImmutable::parse($row->getAttribute('bucket')))) as $index => $bar) {
            if ($index !== '' && isset($bars[(int) $index])) {
                $bars[(int) $index] = $reduce($bar->values());
            }
        }

        return array_values($bars);
    }

    private function barIndex(CarbonImmutable $at): ?int
    {
        $index = (int) floor($this->range->firstBarStart()->diffInMinutes($at) / $this->range->step);

        return $index >= 0 && $index < $this->range->bars ? $index : null;
    }
}
