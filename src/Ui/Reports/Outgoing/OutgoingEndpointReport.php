<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Outgoing;

use Carbon\CarbonImmutable;
use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsCore\Rollup\Bucket;
use Phattarachai\OpsLaravel\Enums\OutgoingOutcome;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\TimeRange;

/**
 * Charts and tables for one outgoing endpoint: calls by outcome and latency per bar, the
 * latency histogram, the outcome and method/status split over the window, and hour of day (7 d).
 */
final readonly class OutgoingEndpointReport
{
    public function __construct(private string $endpoint, private TimeRange $range) {}

    /**
     * @return array{buckets: list<array<string, mixed>>, outcomes: list<array{kind: string, count: int, tone: string}>, histogram: list<array{label: string, count: int, failed: int}>, statuses: list<array<string, mixed>>}
     */
    public function window(): array
    {
        return OpsCache::remember("outgoing:endpoint:{$this->cacheKey()}:{$this->range->key}", fn (): array => $this->buildWindow());
    }

    /** @return list<array{runs: int, failed: int}> */
    public function hourOfDay(): array
    {
        return OpsCache::remember("outgoing:endpoint-hours:{$this->cacheKey()}", function (): array {
            $hours = array_fill(0, 24, ['runs' => 0, 'failed' => 0]);

            foreach (OutgoingRows::rows(OutgoingRows::baseline($this->endpoint)) as $row) {
                $hours[$row->at->hour]['runs'] += $row->stats->counter('calls');
                $hours[$row->at->hour]['failed'] += $row->stats->counter('failed');
            }

            return $hours;
        });
    }

    /** @return array{buckets: list<array<string, mixed>>, outcomes: list<array{kind: string, count: int, tone: string}>, histogram: list<array{label: string, count: int, failed: int}>, statuses: list<array<string, mixed>>} */
    private function buildWindow(): array
    {
        $origin = $this->range->firstBarStart();
        $windowStart = $this->range->isMinuteGrain() ? $this->range->start->startOfMinute() : $this->range->start->startOfHour();
        $bars = [];
        $total = new Bucket;
        $statuses = [];

        foreach (OutgoingRows::rows(OutgoingRows::between($this->range->grain, $origin->min($this->range->start), $this->range->end, $this->endpoint)) as $row) {
            $bar = $this->barIndex($origin, $row->at);

            if ($bar !== null) {
                $bars[$bar] = OutgoingRows::merge($bars[$bar] ?? null, $row->stats);
            }

            if ($row->at->lessThan($windowStart)) {
                continue;
            }

            $total = $total->merge($row->stats);
            $key = "{$row->method}|{$row->status}|{$row->outcome->value}";
            $statuses[$key] = ['method' => $row->method, 'status' => $row->status, 'outcome' => $row->outcome, 'stats' => OutgoingRows::merge($statuses[$key]['stats'] ?? null, $row->stats)];
        }

        return [
            'buckets' => $this->buckets($bars),
            'outcomes' => $this->outcomes($total),
            'histogram' => $this->histogram($total),
            'statuses' => $this->statuses($statuses, $total->counter('calls')),
        ];
    }

    private function barIndex(CarbonImmutable $origin, CarbonImmutable $at): ?int
    {
        $index = intdiv($at->getTimestamp() - $origin->getTimestamp(), $this->range->step * 60);

        return $at->greaterThanOrEqualTo($origin) && $index < $this->range->bars ? $index : null;
    }

    /**
     * @param  array<int, Bucket>  $bars
     * @return list<array<string, mixed>>
     */
    private function buckets(array $bars): array
    {
        return array_map(fn (int $index): array => [
            'label' => $this->range->barLabel($index),
            ...OutgoingRows::outcomeCounts($bars[$index] ?? new Bucket),
            ...OutgoingRows::stats($bars[$index] ?? new Bucket),
        ], range(0, $this->range->bars - 1));
    }

    /** @return list<array{kind: string, count: int, tone: string}> */
    private function outcomes(Bucket $total): array
    {
        return array_values(array_filter(array_map(fn (OutgoingOutcome $outcome): array => [
            'kind' => $outcome->value,
            'count' => $total->counter($outcome->value),
            'tone' => self::tone($outcome),
        ], OutgoingOutcome::cases()), fn (array $outcome): bool => $outcome['count'] > 0));
    }

    /** @return list<array{label: string, count: int, failed: int}> */
    private function histogram(Bucket $total): array
    {
        $bins = LatencyHistogram::normalize($total->histogram);
        $bounds = LatencyHistogram::BOUNDS;

        return array_map(fn (int $bin): array => [
            'label' => $bin < count($bounds) ? '<'.Format::duration($bounds[$bin]) : '≥'.Format::duration($bounds[count($bounds) - 1]),
            'count' => $bins[$bin],
            'failed' => 0,
        ], range(0, LatencyHistogram::BIN_COUNT - 1));
    }

    /**
     * @param  array<string, array{method: string, status: int, outcome: OutgoingOutcome, stats: Bucket}>  $statuses
     * @return list<array<string, mixed>>
     */
    private function statuses(array $statuses, int $calls): array
    {
        $rows = array_map(fn (array $group): array => [
            'key' => "{$group['method']}|{$group['status']}|{$group['outcome']->value}",
            'method' => $group['method'],
            'status' => $group['status'],
            'outcome' => $group['outcome']->value,
            'tone' => self::tone($group['outcome']),
            ...OutgoingRows::stats($group['stats']),
            'share' => Format::percent($group['stats']->counter('calls'), $calls),
        ], array_values($statuses));

        usort($rows, fn (array $a, array $b): int => $b['calls'] <=> $a['calls']);

        return $rows;
    }

    private static function tone(OutgoingOutcome $outcome): string
    {
        return match ($outcome) {
            OutgoingOutcome::Ok => 'ok',
            OutgoingOutcome::Http4xx => 'warn',
            default => 'fail',
        };
    }

    private function cacheKey(): string
    {
        return md5($this->endpoint);
    }
}
