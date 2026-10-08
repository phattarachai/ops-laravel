<?php

declare(strict_types=1);

use Phattarachai\OpsLaravel\Ui\Reports\Schedules\CronCadence;

it('reads cron expressions as a cadence', function (array $expressions, string $kind, string $label, int $sort): void {
    expect(CronCadence::describe($expressions))
        ->kind->toBe($kind)
        ->label->toBe($label)
        ->sort->toBe($sort);
})->with([
    'every minute' => [['* * * * *'], 'frequent', 'Every minute', 1],
    'every five minutes' => [['*/5 * * * *'], 'frequent', 'Every 5 min', 5],
    'quarter hours' => [['0,15,30,45 * * * *'], 'frequent', 'Every 15 min (:00 :15 :30 :45)', 15],
    'hourly' => [['9 * * * *'], 'hourly', 'Hourly at :09', 69],
    'weekday hourly' => [['0 * * * 1-5'], 'hourly', 'Hourly at :00 · Mon–Fri', 60],
    'daily times merged' => [['0 13 * * *', '0 8 * * *'], 'daily', 'Daily 08:00, 13:00', 480],
    'weekdays' => [['0 2 * * 1-5'], 'weekly', 'Mon–Fri 02:00', 1560],
    'weekend' => [['30 6 * * 0,6'], 'weekly', 'Sat–Sun 06:30', 390],
    'most frequent leads' => [['0 3 * * *', '*/10 * * * *'], 'frequent', 'Every 10 min + Daily 03:00', 10],
    'unreadable' => [['0 */2 * * *'], 'other', '0 */2 * * *', 0],
    'day of month' => [['0 0 1 * *'], 'other', '0 0 1 * *', 0],
]);

it('keeps every daily slot and one part per expression for the dashboard to word', function (): void {
    $cadence = CronCadence::describe(['0 3 * * *', '*/10 * * * *']);

    expect($cadence['slots'])->toBe([180])
        ->and($cadence['parts'])->toHaveCount(2)
        ->and($cadence['parts'][0])->toMatchArray(['kind' => 'frequent', 'interval' => 10])
        ->and($cadence['parts'][1])->toMatchArray(['kind' => 'daily', 'slots' => [180]]);
});
