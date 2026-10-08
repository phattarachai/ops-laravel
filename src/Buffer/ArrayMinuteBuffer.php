<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Buffer;

use DateTimeInterface;

final class ArrayMinuteBuffer implements MinuteBuffer
{
    /** @var array<string, array{fields: array<string, int>, maxima: array<string, float>, members: array<string, true>}> */
    private array $minutes = [];

    public function add(string $stream, DateTimeInterface $at, array $increments, array $maxima = [], array $members = []): void
    {
        $key = $this->key($stream, $at);
        $minute = $this->minutes[$key] ?? ['fields' => [], 'maxima' => [], 'members' => []];

        foreach ($increments as $field => $increment) {
            $minute['fields'][$field] = ($minute['fields'][$field] ?? 0) + $increment;
        }

        foreach ($maxima as $member => $value) {
            $minute['maxima'][$member] = max($minute['maxima'][$member] ?? (float) $value, (float) $value);
        }

        foreach ($members as $member) {
            $minute['members'][$member] = true;
        }

        $this->minutes[$key] = $minute;
    }

    public function read(string $stream, DateTimeInterface $minute): MinuteData
    {
        $data = $this->minutes[$this->key($stream, $minute)] ?? null;

        return $data === null ? new MinuteData : new MinuteData($data['fields'], $data['maxima'], array_map(strval(...), array_keys($data['members'])));
    }

    public function forget(string $stream, DateTimeInterface $minute): void
    {
        unset($this->minutes[$this->key($stream, $minute)]);
    }

    private function key(string $stream, DateTimeInterface $at): string
    {
        return $stream.':'.$at->format('YmdHi');
    }
}
