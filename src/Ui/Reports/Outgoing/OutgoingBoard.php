<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Outgoing;

use Carbon\CarbonImmutable;
use Phattarachai\OpsCore\Health\Health;
use Phattarachai\OpsCore\Health\HealthClassifier;
use Phattarachai\OpsCore\Health\HealthInput;
use Phattarachai\OpsCore\Rollup\Bucket;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\TimeFilter;
use Phattarachai\OpsLaravel\Ui\TimeRange;

/**
 * The Outgoing board: one row per endpoint over the window, with health judged against the
 * endpoint's own trailing 7-day error rate (ops-core HealthClassifier).
 */
final readonly class OutgoingBoard
{
    private const int SPARK_POINTS = 20;

    private TimeRange $range;

    public function __construct(private TimeFilter $filter, private HealthClassifier $classifier = new HealthClassifier)
    {
        $this->range = $filter->range();
    }

    /** @return list<array<string, mixed>> */
    public function rows(): array
    {
        return array_values(array_map($this->withUrl(...), $this->cached()));
    }

    /** @return array<string, mixed> */
    public function row(string $endpoint): array
    {
        return $this->withUrl($this->cached()[$endpoint] ?? $this->decorate($endpoint, new Bucket, null, [], []));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{endpoints: int, unhealthy: int, calls: int, failed: int}
     */
    public static function summary(array $rows): array
    {
        $unhealthy = array_filter($rows, fn (array $row): bool => in_array($row['health'], [Health::Incident->value, Health::Degraded->value], true));

        return [
            'endpoints' => count($rows),
            'unhealthy' => count($unhealthy),
            'calls' => (int) array_sum(array_column($rows, 'calls')),
            'failed' => (int) array_sum(array_column($rows, 'failed')),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function cached(): array
    {
        return OpsCache::remember("outgoing:board:{$this->range->key}", fn (): array => $this->build());
    }

    /** @return array<string, array<string, mixed>> */
    private function build(): array
    {
        [$totals, $spark] = $this->window();
        [$baseline, $daily] = $this->baseline();
        $endpoints = array_unique([...array_keys($totals), ...array_keys($baseline)]);
        sort($endpoints);

        $rows = [];

        foreach ($endpoints as $endpoint) {
            $rows[$endpoint] = $this->decorate($endpoint, $totals[$endpoint] ?? new Bucket, $baseline[$endpoint] ?? null, $spark[$endpoint] ?? [], $daily[$endpoint] ?? []);
        }

        return $rows;
    }

    /** @return array{0: array<string, Bucket>, 1: array<string, list<array<string, int>>>} */
    private function window(): array
    {
        $totals = [];
        $spark = [];
        $origin = $this->range->start->getTimestamp();
        $slotSeconds = max(60, (int) ceil(($this->range->end->getTimestamp() - $origin) / self::SPARK_POINTS));

        foreach (OutgoingRows::rows(OutgoingRows::window($this->range)) as $row) {
            $totals[$row->endpoint] = OutgoingRows::merge($totals[$row->endpoint] ?? null, $row->stats);
            $spark[$row->endpoint] ??= array_fill(0, self::SPARK_POINTS, []);
            $slot = min(self::SPARK_POINTS - 1, max(0, intdiv($row->at->getTimestamp() - $origin, $slotSeconds)));
            $spark[$row->endpoint][$slot][$row->outcome->value] = ($spark[$row->endpoint][$slot][$row->outcome->value] ?? 0) + $row->stats->counter('calls');
        }

        return [$totals, $spark];
    }

    /** @return array{0: array<string, array{calls: int, failed: int}>, 1: array<string, list<float|null>>} */
    private function baseline(): array
    {
        $totals = [];
        $days = [];

        foreach (OutgoingRows::rows(OutgoingRows::baseline()) as $row) {
            $day = $row->at->toDateString();
            $totals[$row->endpoint]['calls'] = ($totals[$row->endpoint]['calls'] ?? 0) + $row->stats->counter('calls');
            $totals[$row->endpoint]['failed'] = ($totals[$row->endpoint]['failed'] ?? 0) + $row->stats->counter('failed');
            $days[$row->endpoint][$day]['calls'] = ($days[$row->endpoint][$day]['calls'] ?? 0) + $row->stats->counter('calls');
            $days[$row->endpoint][$day]['failed'] = ($days[$row->endpoint][$day]['failed'] ?? 0) + $row->stats->counter('failed');
        }

        return [$totals, array_map($this->dailyErrorPercents(...), $days)];
    }

    /**
     * @param  array<string, array{calls?: int, failed?: int}>  $days
     * @return list<float|null>
     */
    private function dailyErrorPercents(array $days): array
    {
        $today = CarbonImmutable::today();

        return array_map(function (int $offset) use ($days, $today): ?float {
            $day = $days[$today->subDays($offset)->toDateString()] ?? [];
            $calls = $day['calls'] ?? 0;

            return $calls > 0 ? round(($day['failed'] ?? 0) / $calls * 100, 1) : null;
        }, range(OutgoingRows::BASELINE_DAYS - 1, 0));
    }

    /**
     * @param  array{calls: int, failed: int}|null  $baseline
     * @param  list<array<string, int>>  $spark
     * @param  list<float|null>  $errorDays
     * @return array<string, mixed>
     */
    private function decorate(string $endpoint, Bucket $window, ?array $baseline, array $spark, array $errorDays): array
    {
        $stats = OutgoingRows::stats($window);
        $baselinePercent = $baseline !== null && $baseline['calls'] > 0 ? round($baseline['failed'] / $baseline['calls'] * 100, 1) : null;
        $verdict = $this->classifier->classify(new HealthInput($stats['calls'], $stats['failed'], $stats['p50'], $stats['p95'], baselineErrorPct: $baselinePercent));

        return [
            'endpoint' => $endpoint,
            'token' => Format::base64url($endpoint),
            ...$stats,
            'error_pct' => $verdict->errorPct,
            'baseline_error_pct' => $baselinePercent,
            'deviation' => $verdict->deviation,
            'pinned' => $verdict->pinnedAtCeiling,
            'health' => $verdict->health->value,
            'health_label' => $verdict->health->label(),
            'health_tone' => $verdict->health->tone(),
            'health_rank' => $verdict->health->rank(),
            'is_dark' => $stats['calls'] === 0,
            'outcomes' => OutgoingRows::outcomeCounts($window),
            'spark' => $spark,
            'error_days' => $errorDays,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withUrl(array $row): array
    {
        return [...$row, 'url' => route('ops.outgoing.show', ['token' => $row['token'], ...$this->filter->params()])];
    }
}
