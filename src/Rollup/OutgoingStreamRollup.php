<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Rollup;

use Carbon\CarbonImmutable;
use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsLaravel\Buffer\MinuteData;
use Phattarachai\OpsLaravel\Models\OutgoingRollup;
use Phattarachai\OpsLaravel\Recorders\OutgoingRecorder;

class OutgoingStreamRollup extends StreamRollup
{
    private const array DIMENSIONS = ['endpoint', 'method', 'status', 'outcome'];

    public function stream(): string
    {
        return OutgoingRecorder::STREAM;
    }

    public function foldHours(CarbonImmutable $fromHour, CarbonImmutable $toHour): int
    {
        $groups = [];

        OutgoingRollup::query()
            ->where('grain', 'minute')
            ->where('bucket', '>=', $fromHour)
            ->where('bucket', '<', $toHour->addHour())
            ->orderBy('id')
            ->each(function (OutgoingRollup $row) use (&$groups): void {
                $key = $row->bucket->startOfHour()->format('Y-m-d H:i:s').'|'.MinuteData::dims([$row->endpoint, $row->method, $row->status, $row->outcome]);
                $groups[$key] ??= ['bucket' => $row->bucket->startOfHour(), 'endpoint' => $row->endpoint, 'method' => $row->method, 'status' => $row->status, 'outcome' => $row->outcome, 'calls' => 0, 'timed' => 0, 'sum_ms' => 0, 'max_ms' => 0, 'histogram' => LatencyHistogram::empty()];
                $groups[$key]['calls'] += $row->calls;
                $groups[$key]['timed'] += $row->timed;
                $groups[$key]['sum_ms'] += $row->sum_ms;
                $groups[$key]['max_ms'] = max($groups[$key]['max_ms'], $row->max_ms);
                $groups[$key]['histogram'] = LatencyHistogram::merge($groups[$key]['histogram'], $row->histogram ?? []);
            });

        $rows = array_map(fn (array $group): array => $this->row('hour', $group), array_values($groups));

        foreach (array_chunk($rows, 500) as $chunk) {
            OutgoingRollup::query()->upsert($chunk, ['grain', 'bucket', ...self::DIMENSIONS], ['calls', 'timed', 'sum_ms', 'max_ms', 'p50_ms', 'p95_ms', 'histogram', 'updated_at']);
        }

        return count($rows);
    }

    protected function write(CarbonImmutable $minute, MinuteData $data): void
    {
        $rows = [];

        foreach ($data->grouped() as $dims => $metrics) {
            [$endpoint, $method, $status, $outcome] = array_pad(MinuteData::split($dims), 4, '');

            $rows[] = $this->row('minute', [
                'bucket' => $minute,
                'endpoint' => $endpoint,
                'method' => $method,
                'status' => (int) $status,
                'outcome' => $outcome,
                'calls' => $metrics['c'] ?? 0,
                'timed' => $metrics['t'] ?? 0,
                'sum_ms' => $metrics['ms'] ?? 0,
                'max_ms' => (int) $data->max($dims),
                'histogram' => HistogramRows::fromMetrics($metrics),
            ]);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            OutgoingRollup::query()->insert($chunk);
        }
    }

    /**
     * @param  array{bucket: CarbonImmutable, endpoint: string, method: string, status: int, outcome: string, calls: int, timed: int, sum_ms: int, max_ms: int, histogram: list<int>}  $group
     * @return array<model-property<OutgoingRollup>, mixed>
     */
    protected function row(string $grain, array $group): array
    {
        $bucket = HistogramRows::bucket($group['timed'], $group['sum_ms'], $group['max_ms'], $group['histogram']);

        return [
            ...$group,
            ...HistogramRows::percentiles($bucket),
            'grain' => $grain,
            'histogram' => json_encode($group['histogram']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
