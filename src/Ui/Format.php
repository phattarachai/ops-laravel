<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui;

use Carbon\CarbonInterface;

final class Format
{
    public static function duration(?float $seconds): string
    {
        return match (true) {
            $seconds === null => '—',
            $seconds <= 0 => '0',
            $seconds < 1 => round($seconds * 1000).'ms',
            $seconds < 60 => round($seconds, $seconds < 10 ? 1 : 0).'s',
            $seconds < 3600 => round($seconds / 60, 1).'m',
            default => round($seconds / 3600, 1).'h',
        };
    }

    public static function percent(int $part, int $whole): string
    {
        $percent = $whole > 0 ? $part / $whole * 100 : 0;

        return $part > 0 && $percent < 0.1 ? '<0.1%' : round($percent, 1).'%';
    }

    public static function count(float $value): string
    {
        return $value >= 1000 ? round($value / 1000, 1).'k' : (string) round($value);
    }

    /** A localised date label, e.g. `j M H:i` → "28 Sep 12:02" / "28 ก.ย. 12:02". */
    public static function date(?CarbonInterface $date, string $format = 'j M H:i'): ?string
    {
        return $date?->copy()->locale(self::locale())->translatedFormat($format);
    }

    public static function locale(): string
    {
        return (string) (config('ops.locale') ?? app()->getLocale());
    }

    public static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function fromBase64url(string $token): ?string
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
