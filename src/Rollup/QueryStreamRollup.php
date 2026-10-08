<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Rollup;

use Carbon\CarbonImmutable;
use Phattarachai\OpsLaravel\Buffer\MinuteData;
use Phattarachai\OpsLaravel\Models\SlowQuery;
use Phattarachai\OpsLaravel\Recorders\QueryRecorder;

class QueryStreamRollup extends StreamRollup
{
    public function stream(): string
    {
        return QueryRecorder::STREAM;
    }

    protected function write(CarbonImmutable $minute, MinuteData $data): void
    {
        $hour = $minute->startOfHour();
        $samples = $this->samples($data);
        $groups = $data->grouped();
        $existing = SlowQuery::query()->where('bucket', $hour)->whereIn('hash', array_keys($groups))->get()->keyBy('hash');
        $now = now();

        $rows = [];

        foreach ($groups as $hash => $metrics) {
            $current = $existing->get($hash);
            $sample = $samples[$hash] ?? ['connection' => '', 'sql' => '', 'location' => null];

            $rows[] = [
                'bucket' => $hour,
                'hash' => $hash,
                'connection' => $current->connection ?? $sample['connection'],
                'sql' => $current->sql ?? $sample['sql'],
                'location' => $current->location ?? $sample['location'],
                'count' => ($current->count ?? 0) + ($metrics['c'] ?? 0),
                'sum_ms' => ($current->sum_ms ?? 0) + ($metrics['ms'] ?? 0),
                'max_ms' => max($current->max_ms ?? 0, (int) $data->max($hash)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            SlowQuery::query()->upsert($chunk, ['bucket', 'hash'], ['count', 'sum_ms', 'max_ms', 'updated_at']);
        }
    }

    /** @return array<string, array{connection: string, sql: string, location: string|null}> */
    private function samples(MinuteData $data): array
    {
        $samples = [];

        foreach ($data->members as $member) {
            $decoded = json_decode($member, true);

            if (is_array($decoded) && count($decoded) === 4) {
                $samples[(string) $decoded[0]] = ['connection' => (string) $decoded[1], 'sql' => (string) $decoded[2], 'location' => $decoded[3] === null ? null : (string) $decoded[3]];
            }
        }

        return $samples;
    }
}
