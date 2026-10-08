<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Rollup;

use Carbon\CarbonImmutable;
use Phattarachai\OpsLaravel\Buffer\MinuteData;
use Phattarachai\OpsLaravel\Models\SignalRollup;
use Phattarachai\OpsLaravel\Models\SignalValue;
use Phattarachai\OpsLaravel\Recorders\SignalRecorder;

class SignalStreamRollup extends StreamRollup
{
    public function stream(): string
    {
        return SignalRecorder::STREAM;
    }

    protected function write(CarbonImmutable $minute, MinuteData $data): void
    {
        $now = now();
        $counts = [];

        foreach ($data->grouped() as $dims => $metrics) {
            [$signal, $key] = array_pad(MinuteData::split($dims), 2, '');
            $counts[] = ['bucket' => $minute, 'signal' => $signal, 'key' => $key, 'count' => $metrics['c'] ?? 0, 'created_at' => $now, 'updated_at' => $now];
        }

        foreach (array_chunk($counts, 500) as $chunk) {
            SignalRollup::query()->insert($chunk);
        }

        foreach (array_chunk($this->values($minute, $data), 500) as $chunk) {
            SignalValue::query()->insert($chunk);
        }
    }

    /** @return list<array{bucket: CarbonImmutable, signal: string, key: string, field: string, value: string}> */
    private function values(CarbonImmutable $minute, MinuteData $data): array
    {
        $values = [];

        foreach ($data->members as $member) {
            $decoded = json_decode($member, true);

            if (! is_array($decoded) || count($decoded) !== 3) {
                continue;
            }

            [$dims, $field, $value] = array_map(strval(...), $decoded);
            [$signal, $key] = array_pad(MinuteData::split($dims), 2, '');
            $values["{$dims}|{$field}|{$value}"] = ['bucket' => $minute, 'signal' => $signal, 'key' => $key, 'field' => $field, 'value' => $value];
        }

        return array_values($values);
    }
}
