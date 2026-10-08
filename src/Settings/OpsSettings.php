<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Settings;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Phattarachai\OpsLaravel\Models\Setting;
use Throwable;

/**
 * Runtime settings: a stored ops_settings row wins over the config('ops.*') default of the same
 * key. Shared through the cache for 60 s and memoised in-process for 30 s.
 */
class OpsSettings
{
    public const string CACHE_KEY = 'ops:settings';

    private const int SHARED_TTL = 60;

    private const int LOCAL_TTL = 30;

    /** @var array<string, mixed>|null */
    private ?array $stored = null;

    private int $loadedAt = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->stored();

        return array_key_exists($key, $stored) ? $stored[$key] : config("ops.{$key}", $default);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /** @return list<string> */
    public function list(string $key): array
    {
        return array_values(array_filter(Arr::wrap($this->get($key, [])), is_string(...)));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->stored());
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $this->encode($key, $value)]);

        $this->flush();
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();

        $this->flush();
    }

    public function flush(): void
    {
        $this->stored = null;
        rescue(fn () => Cache::forget(self::CACHE_KEY), report: false);
    }

    public function isSecret(string $key): bool
    {
        return in_array($key, (array) config('ops.secret_settings', []), true);
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        if ($this->stored === null || time() - $this->loadedAt >= self::LOCAL_TTL) {
            $this->stored = $this->load();
            $this->loadedAt = time();
        }

        return $this->stored;
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        try {
            $rows = Cache::remember(self::CACHE_KEY, self::SHARED_TTL, fn (): array => Setting::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            return [];
        }

        $decoded = [];

        foreach ((array) $rows as $key => $value) {
            $decoded[(string) $key] = $this->decode((string) $key, $value);
        }

        return $decoded;
    }

    private function encode(string $key, mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->isSecret($key) ? Crypt::encryptString($json) : $json;
    }

    private function decode(string $key, mixed $value): mixed
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return json_decode($this->isSecret($key) ? Crypt::decryptString($value) : $value, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
    }
}
