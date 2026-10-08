<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;
use Phattarachai\OpsLaravel\Buffer\RedisMinuteBuffer;

beforeEach(function (): void {
    $this->app['config']->set('database.redis.client', extension_loaded('redis') ? 'phpredis' : 'predis');

    try {
        Redis::connection()->ping();
    } catch (Throwable) {
        $this->markTestSkipped('Redis is not reachable.');
    }

    $this->buffer = new RedisMinuteBuffer(prefix: 'ops-test-'.bin2hex(random_bytes(4)));
    $this->minute = CarbonImmutable::parse('2026-10-08 10:15:00');
});

it('increments counters, keeps the greater max and collects members', function (): void {
    $this->buffer->add('req', $this->minute, ['a#c' => 1, 'a#ms' => 40], ['a' => 40], ['x']);
    $this->buffer->add('req', $this->minute->addSeconds(30), ['a#c' => 1, 'a#ms' => 25], ['a' => 25], ['x', 'y']);

    $data = $this->buffer->read('req', $this->minute);

    expect($data->fields)->toBe(['a#c' => 2, 'a#ms' => 65])
        ->and($data->max('a'))->toBe(40.0)
        ->and(collect($data->members)->sort()->values()->all())->toBe(['x', 'y']);

    $this->buffer->forget('req', $this->minute);

    expect($this->buffer->read('req', $this->minute)->isEmpty())->toBeTrue();
});
