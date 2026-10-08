<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Recorders;

use Phattarachai\OpsLaravel\Buffer\MinuteBuffer;
use Phattarachai\OpsLaravel\Buffer\MinuteData;
use Phattarachai\OpsLaravel\Ops;

/**
 * Host-defined counters (`Ops::signal()`): one count per signal × key per minute, plus the
 * distinct meta values a rule can count (`distinctField`).
 */
class SignalRecorder
{
    public const string STREAM = 'sig';

    public function __construct(protected MinuteBuffer $buffer) {}

    /** @param  array<string, scalar|null>  $meta */
    public function record(string $signal, string $key = '', array $meta = [], int $count = 1): void
    {
        rescue(function () use ($signal, $key, $meta, $count): void {
            if (! Ops::recording()) {
                return;
            }

            $dims = MinuteData::dims([mb_substr($signal, 0, 100), mb_substr($key, 0, 255)]);
            $members = [];

            foreach ($meta as $field => $value) {
                $members[] = (string) json_encode([$dims, mb_substr((string) $field, 0, 64), mb_substr((string) $value, 0, 255)], JSON_UNESCAPED_UNICODE);
            }

            $this->buffer->add(self::STREAM, now(), [MinuteData::field($dims, 'c') => $count], members: $members);
        }, report: false);
    }
}
