<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Samplers;

use Phattarachai\OpsCore\Alerts\ProbeLevel;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Signals\BuiltInSignals;
use Phattarachai\OpsLaravel\Ui\Reports\Schedules\ScheduleBoard;
use Phattarachai\OpsLaravel\Ui\TimeRange;

/**
 * Publishes each scheduled task's health over the last 24 h as the `schedule.health` probe
 * (failing = critical, missed runs = warning), so a state rule can alert on it.
 */
final class ScheduleHealthProbe
{
    public function publish(): int
    {
        $tasks = array_filter(ScheduleBoard::tasks(TimeRange::fromPreset('24h')), fn (array $task): bool => ! $task['silenced']);

        foreach ($tasks as $task) {
            Ops::probe(BuiltInSignals::SCHEDULE_HEALTH, $task['task'], $this->level($task['health']), (float) ($task['missed'] ?? 0), $this->message($task));
        }

        return count($tasks);
    }

    private function level(string $health): ProbeLevel
    {
        return match ($health) {
            'failing' => ProbeLevel::Critical,
            'missed' => ProbeLevel::Warning,
            default => ProbeLevel::Ok,
        };
    }

    /** @param  array<string, mixed>  $task */
    private function message(array $task): string
    {
        return match ($task['health']) {
            'failing' => "{$task['task']} failed {$task['failed']}×",
            'missed' => "{$task['task']} missed {$task['missed']} run(s)",
            default => (string) $task['task'],
        };
    }
}
