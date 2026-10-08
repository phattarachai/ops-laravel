<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Incidents;

use Carbon\CarbonImmutable;
use Phattarachai\OpsCore\Alerts\IncidentStatus;
use Phattarachai\OpsCore\Alerts\ProbeLevel;
use Phattarachai\OpsCore\Alerts\RuleType;
use Phattarachai\OpsLaravel\Models\Incident;
use Phattarachai\OpsLaravel\Ui\Format;

final class IncidentPresenter
{
    /** @return array<string, mixed> */
    public static function row(Incident $incident): array
    {
        return [
            'id' => $incident->id,
            'url' => route('ops.incidents.show', ['token' => $incident->id]),
            'rule_id' => $incident->rule_id,
            'rule_name' => $incident->rule?->name,
            'key' => $incident->key,
            'status' => $incident->status->value,
            'is_open' => $incident->status === IncidentStatus::Open,
            'severity' => $incident->severity->value,
            'opened' => Format::date($incident->opened_at),
            'opened_iso' => $incident->opened_at->toIso8601String(),
            'last_breach' => Format::date($incident->last_breached_at),
            'last_breach_iso' => $incident->last_breached_at->toIso8601String(),
            'resolved' => Format::date($incident->resolved_at),
            'resolved_iso' => $incident->resolved_at?->toIso8601String(),
            'peak' => self::value($incident, $incident->peak_value),
            'peak_value' => $incident->peak_value,
            'last' => self::value($incident, $incident->last_value),
            'notifications' => $incident->notifications,
            'duration' => Format::duration(self::durationSeconds($incident)),
            'duration_seconds' => self::durationSeconds($incident),
        ];
    }

    public static function value(Incident $incident, float $value): string
    {
        return match ($incident->rule?->type) {
            RuleType::Rate => round($value, 1).'%',
            RuleType::Absence => Format::duration($value),
            RuleType::State => self::level($value),
            default => $value === floor($value) ? number_format($value) : number_format($value, 1),
        };
    }

    private static function level(float $rank): string
    {
        foreach (ProbeLevel::cases() as $level) {
            if ($level->rank() === (int) $rank) {
                return $level->value;
            }
        }

        return (string) $rank;
    }

    private static function durationSeconds(Incident $incident): int
    {
        $end = $incident->resolved_at ?? CarbonImmutable::now();

        return max(0, (int) $incident->opened_at->diffInSeconds($end));
    }
}
