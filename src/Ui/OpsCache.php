<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui;

use Illuminate\Support\Facades\Cache;

/**
 * Every report reads through here: stale-while-revalidate (fresh 60 s, stale to 300 s),
 * versioned so refresh busts it, and closed days cached per date outside the version.
 */
final class OpsCache
{
    public const int FRESH_SECONDS = 60;

    public const int STALE_SECONDS = 300;

    private const string VERSION_KEY = 'ops:ui:cache-version';

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    public static function remember(string $key, callable $callback): mixed
    {
        return Cache::flexible(self::key($key), [self::FRESH_SECONDS, self::STALE_SECONDS], $callback);
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    public static function rememberClosedDays(string $key, callable $callback): mixed
    {
        return Cache::flexible("ops:ui:closed:{$key}:".today()->toDateString(), [3600, 7200], $callback);
    }

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    public static function bust(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    private static function key(string $key): string
    {
        return 'ops:ui:v'.self::version().":{$key}";
    }
}
