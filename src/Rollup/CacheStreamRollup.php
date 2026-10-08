<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Rollup;

use Carbon\CarbonImmutable;
use Phattarachai\OpsLaravel\Buffer\MinuteData;
use Phattarachai\OpsLaravel\Models\CacheRollup;
use Phattarachai\OpsLaravel\Recorders\CacheRecorder;

class CacheStreamRollup extends StreamRollup
{
    public function stream(): string
    {
        return CacheRecorder::STREAM;
    }

    protected function write(CarbonImmutable $minute, MinuteData $data): void
    {
        $hour = $minute->startOfHour();
        $groups = $data->grouped();
        $existing = CacheRollup::query()->where('bucket', $hour)->whereIn('key', array_map(strval(...), array_keys($groups)))->get()->keyBy('key');
        $now = now();
        $rows = [];

        foreach ($groups as $key => $metrics) {
            $current = $existing->get($key);

            $rows[] = [
                'bucket' => $hour,
                'key' => (string) $key,
                'hits' => ($current->hits ?? 0) + ($metrics['h'] ?? 0),
                'misses' => ($current->misses ?? 0) + ($metrics['m'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            CacheRollup::query()->upsert($chunk, ['bucket', 'key'], ['hits', 'misses', 'updated_at']);
        }
    }
}
