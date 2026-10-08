<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Buffer;

use DateTimeInterface;

interface MinuteBuffer
{
    /**
     * @param  array<string, int>  $increments  hash field => increment
     * @param  array<string, float|int>  $maxima  member => value kept only when greater
     * @param  list<string>  $members  set members
     */
    public function add(string $stream, DateTimeInterface $at, array $increments, array $maxima = [], array $members = []): void;

    public function read(string $stream, DateTimeInterface $minute): MinuteData;

    public function forget(string $stream, DateTimeInterface $minute): void;
}
