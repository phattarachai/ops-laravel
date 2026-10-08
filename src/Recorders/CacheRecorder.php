<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Recorders;

use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Cache\Events\CacheHit;
use Phattarachai\OpsLaravel\Buffer\MinuteBuffer;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

/**
 * Sampled cache hits and misses per key pattern (digits and uuids masked). Counts are scaled
 * back up by the sample rate when they are recorded.
 */
class CacheRecorder
{
    public const string STREAM = 'cch';

    private const array IGNORED_PREFIXES = ['ops:', 'illuminate:', 'laravel:', 'framework/', 'telescope:', 'pulse:', 'horizon:'];

    public function __construct(
        protected MinuteBuffer $buffer,
        protected OpsSettings $settings,
    ) {}

    public static function pattern(string $key): string
    {
        return mb_substr((string) preg_replace(['/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '/\d+/'], ['{uuid}', '{n}'], $key), 0, 255);
    }

    public function record(CacheEvent $event): void
    {
        if ($this->ignored($event->key) || ! Ops::recording()) {
            return;
        }

        rescue(function () use ($event): void {
            $rate = (float) $this->settings->get('recorders.cache.sample_rate', 0.1);

            if (! $this->settings->bool('recorders.cache.enabled') || $rate <= 0 || mt_rand() / mt_getrandmax() > $rate) {
                return;
            }

            $weight = max(1, (int) round(1 / min(1.0, $rate)));
            $metric = $event instanceof CacheHit ? 'h' : 'm';

            $this->buffer->add(self::STREAM, now(), [self::pattern($event->key).'#'.$metric => $weight]);
        }, report: false);
    }

    private function ignored(string $key): bool
    {
        foreach (self::IGNORED_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
