<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Jobs;

use Carbon\CarbonImmutable;
use Phattarachai\OpsLaravel\Ui\Format;
use Throwable;

/** The 14-day history columns and the `?day=` of the hourly / timeline views, shared by Jobs and Schedules. */
final class Days
{
    public const int HISTORY = 14;

    /**
     * @return list<array{date: string, label: string, weekday: string, weekend: bool, today: bool}>
     */
    public static function history(int $days = self::HISTORY): array
    {
        return array_map(function (int $offset): array {
            $date = CarbonImmutable::today()->subDays($offset);

            return [
                'date' => $date->toDateString(),
                'label' => (string) Format::date($date, 'j M'),
                'weekday' => (string) Format::date($date, 'D'),
                'weekend' => $date->isWeekend(),
                'today' => $date->isToday(),
            ];
        }, range($days - 1, 0));
    }

    /** A past `Y-m-d` day; anything malformed or in the future reads as today. */
    public static function pick(mixed $day): CarbonImmutable
    {
        $today = CarbonImmutable::today();

        if (! is_string($day) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
            return $today;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $day);
        } catch (Throwable) {
            return $today;
        }

        return ! $date instanceof CarbonImmutable || $date->toDateString() !== $day || $date->greaterThan($today) ? $today : $date;
    }

    /**
     * @return array{date: string, today: string, label: string}
     */
    public static function header(CarbonImmutable $date): array
    {
        return [
            'date' => $date->toDateString(),
            'today' => CarbonImmutable::today()->toDateString(),
            'label' => (string) Format::date($date, 'D j M Y'),
        ];
    }
}
