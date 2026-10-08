<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Buffer;

use DateTimeInterface;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Redis;

/**
 * Per stream and minute: a hash of counters (`:h`), a sorted set of maxima (`:m`, ZADD GT, so
 * Redis 6.2+) and a set of members (`:s`), all written in one pipeline with a TTL.
 */
final readonly class RedisMinuteBuffer implements MinuteBuffer
{
    public function __construct(
        private ?string $connection = null,
        private string $prefix = 'ops',
        private int $ttlSeconds = 10800,
    ) {}

    public function add(string $stream, DateTimeInterface $at, array $increments, array $maxima = [], array $members = []): void
    {
        $base = $this->base($stream, $at);
        $redis = $this->redis();
        $phpRedis = $redis instanceof PhpRedisConnection;

        $commands = function (mixed $pipe) use ($base, $increments, $maxima, $members, $phpRedis): void {
            foreach ($increments as $field => $increment) {
                $pipe->hIncrBy("{$base}:h", $field, $increment);
            }
            $pipe->expire("{$base}:h", $this->ttlSeconds);

            foreach ($maxima as $member => $value) {
                $phpRedis ? $pipe->zAdd("{$base}:m", ['GT'], $value, $member) : $pipe->zadd("{$base}:m", 'GT', $value, $member);
            }

            if ($maxima !== []) {
                $pipe->expire("{$base}:m", $this->ttlSeconds);
            }

            if ($members !== []) {
                $pipe->sAdd("{$base}:s", ...$members);
                $pipe->expire("{$base}:s", $this->ttlSeconds);
            }
        };

        $redis instanceof PhpRedisConnection ? $redis->pipeline($commands) : $redis->command('pipeline', [$commands]);
    }

    public function read(string $stream, DateTimeInterface $minute): MinuteData
    {
        $base = $this->base($stream, $minute);
        $redis = $this->redis();

        $fields = array_map(intval(...), (array) ($redis->hGetAll("{$base}:h") ?: []));
        $maxima = array_map(floatval(...), (array) ($redis->zRange("{$base}:m", 0, -1, ['withscores' => true]) ?: []));
        $members = array_values(array_map(strval(...), (array) ($redis->sMembers("{$base}:s") ?: [])));

        return new MinuteData($fields, $maxima, $members);
    }

    public function forget(string $stream, DateTimeInterface $minute): void
    {
        $base = $this->base($stream, $minute);

        $this->redis()->del("{$base}:h", "{$base}:m", "{$base}:s");
    }

    private function base(string $stream, DateTimeInterface $at): string
    {
        return "{$this->prefix}:{$stream}:{$at->format('YmdHi')}";
    }

    private function redis(): Connection
    {
        return Redis::connection($this->connection);
    }
}
