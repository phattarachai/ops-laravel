<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Schedules;

/**
 * Reads a task's cron expressions as a cadence: a kind (`frequent` = more than once an hour,
 * `hourly`, `daily`, `weekly`, or `other` for what it cannot read), a sort key, the daily slots and
 * one part per expression that the dashboard words in its locale (`label` is the English wording).
 * Several all-daily expressions merge into one time list; otherwise the most frequent one leads.
 *
 * @phpstan-type Part array{kind: string, interval: ?int, minute: ?int, marks: ?list<int>, slots: list<int>, days: ?list<int>, expression: string, sort: int}
 */
final class CronCadence
{
    /** @var list<string> */
    public const array KINDS = ['frequent', 'hourly', 'daily', 'weekly', 'other'];

    /** @var list<string> */
    private const array DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    /**
     * @param  list<string>  $expressions
     * @return array{kind: string, label: string, interval: ?int, slots: list<int>, days: ?list<int>, sort: int, parts: list<array<string, mixed>>}
     */
    public static function describe(array $expressions): array
    {
        $parts = array_map(self::parse(...), $expressions === [] ? ['* * * * *'] : $expressions);
        usort($parts, fn (array $a, array $b): int => array_search($a['kind'], self::KINDS, true) <=> array_search($b['kind'], self::KINDS, true));

        $merged = self::allDaily($parts) ? [self::mergeDaily($parts)] : $parts;
        $first = $merged[0];

        return [
            'kind' => $first['kind'],
            'label' => implode(' + ', array_map(self::label(...), $merged)),
            'interval' => $first['interval'],
            'slots' => array_values(array_unique(array_merge(...array_column($parts, 'slots')))),
            'days' => $first['days'],
            'sort' => $first['sort'],
            'parts' => $merged,
        ];
    }

    /**
     * @param  Part  $part
     */
    public static function label(array $part): string
    {
        $days = self::dayLabel($part['days']);
        $suffix = $days === '' ? '' : " · {$days}";
        $times = implode(', ', array_map(self::clock(...), $part['slots']));

        return match (true) {
            $part['kind'] === 'frequent' && $part['interval'] === 1 => "Every minute{$suffix}",
            $part['kind'] === 'frequent' && $part['marks'] !== null => "Every {$part['interval']} min (".implode(' ', array_map(fn (int $minute): string => sprintf(':%02d', $minute), $part['marks'])).")$suffix",
            $part['kind'] === 'frequent' => "Every {$part['interval']} min{$suffix}",
            $part['kind'] === 'hourly' => sprintf('Hourly at :%02d', (int) $part['minute']).$suffix,
            $part['kind'] === 'daily' => "Daily {$times}",
            $part['kind'] === 'weekly' => "{$days} {$times}",
            default => $part['expression'],
        };
    }

    /**
     * @return Part
     */
    private static function parse(string $expression): array
    {
        $fields = preg_split('/\s+/', trim($expression)) ?: [];

        if (count($fields) !== 5 || $fields[2] !== '*' || $fields[3] !== '*') {
            return self::other($expression);
        }

        [$minute, $hour, , , $weekday] = $fields;
        $days = self::days($weekday);

        if ($days === false) {
            return self::other($expression);
        }

        return $hour === '*'
            ? self::withinHour($minute, $days, $expression) ?? self::other($expression)
            : self::atTimes($minute, $hour, $days, $expression) ?? self::other($expression);
    }

    /**
     * @param  list<int>|null  $days
     * @return Part|null
     */
    private static function atTimes(string $minute, string $hour, ?array $days, string $expression): ?array
    {
        $hours = self::numbers($hour, 23);
        $minutes = self::numbers($minute, 59);

        if ($hours === null || $minutes === null) {
            return null;
        }

        $slots = [];

        foreach ($hours as $h) {
            foreach ($minutes as $m) {
                $slots[] = $h * 60 + $m;
            }
        }

        sort($slots);

        return self::part($days === null ? 'daily' : 'weekly', $expression, slots: $slots, days: $days, sort: ($days[0] ?? 0) * 1440 + $slots[0]);
    }

    /**
     * @param  list<int>|null  $days
     * @return Part|null
     */
    private static function withinHour(string $minute, ?array $days, string $expression): ?array
    {
        if ($minute === '*') {
            return self::part('frequent', $expression, interval: 1, days: $days, sort: 1);
        }

        if (preg_match('/^\*\/(\d+)$/', $minute, $match) === 1) {
            return self::part('frequent', $expression, interval: (int) $match[1], days: $days, sort: (int) $match[1]);
        }

        $minutes = self::numbers($minute, 59);

        return match (true) {
            $minutes === null => null,
            count($minutes) === 1 => self::part('hourly', $expression, interval: 60, minute: $minutes[0], days: $days, sort: 60 + $minutes[0]),
            default => self::part('frequent', $expression, interval: intdiv(60, count($minutes)), marks: $minutes, days: $days, sort: intdiv(60, count($minutes))),
        };
    }

    /**
     * @param  list<Part>  $parts
     */
    private static function allDaily(array $parts): bool
    {
        return count($parts) > 1 && array_filter($parts, fn (array $part): bool => $part['kind'] !== 'daily') === [];
    }

    /**
     * @param  list<Part>  $daily
     * @return Part
     */
    private static function mergeDaily(array $daily): array
    {
        $slots = array_values(array_unique(array_merge(...array_column($daily, 'slots'))));
        sort($slots);

        return self::part('daily', implode(' · ', array_column($daily, 'expression')), slots: $slots, sort: $slots[0]);
    }

    /**
     * @return Part
     */
    private static function other(string $expression): array
    {
        return self::part('other', $expression);
    }

    /**
     * @param  list<int>|null  $marks
     * @param  list<int>  $slots
     * @param  list<int>|null  $days
     * @return Part
     */
    private static function part(string $kind, string $expression, ?int $interval = null, ?int $minute = null, ?array $marks = null, array $slots = [], ?array $days = null, int $sort = 0): array
    {
        return ['kind' => $kind, 'interval' => $interval, 'minute' => $minute, 'marks' => $marks, 'slots' => $slots, 'days' => $days, 'expression' => $expression, 'sort' => $sort];
    }

    /**
     * @return list<int>|null|false null = every day, false = unreadable
     */
    private static function days(string $weekday): array|null|false
    {
        if ($weekday === '*') {
            return null;
        }

        $days = self::numbers($weekday, 7);

        if ($days === null) {
            return false;
        }

        $days = array_values(array_unique(array_map(fn (int $day): int => $day % 7, $days)));
        sort($days);

        return $days;
    }

    /**
     * @param  list<int>|null  $days
     */
    private static function dayLabel(?array $days): string
    {
        return match (true) {
            $days === null => '',
            $days === [1, 2, 3, 4, 5] => 'Mon–Fri',
            $days === [0, 6] => 'Sat–Sun',
            default => implode(' ', array_map(fn (int $day): string => self::DAY_NAMES[$day], $days)),
        };
    }

    /**
     * @return list<int>|null
     */
    private static function numbers(string $field, int $max): ?array
    {
        $numbers = [];

        foreach (explode(',', $field) as $part) {
            if (preg_match('/^(\d+)(?:-(\d+))?$/', $part, $match) !== 1) {
                return null;
            }

            $from = (int) $match[1];
            $to = isset($match[2]) ? (int) $match[2] : $from;

            if ($to > $max || $from > $to) {
                return null;
            }

            array_push($numbers, ...range($from, $to));
        }

        $numbers = array_values(array_unique($numbers));
        sort($numbers);

        return $numbers;
    }

    private static function clock(int $minuteOfDay): string
    {
        return sprintf('%02d:%02d', intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }
}
