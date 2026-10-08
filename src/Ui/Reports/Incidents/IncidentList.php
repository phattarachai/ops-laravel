<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Incidents;

use Illuminate\Database\Eloquent\Builder;
use Phattarachai\OpsCore\Alerts\IncidentStatus;
use Phattarachai\OpsLaravel\Models\Incident;
use Phattarachai\OpsLaravel\Ui\OpsCache;

/**
 * Every open incident, then the newest resolved ones (capped), newest first within each.
 */
final class IncidentList
{
    public const int RESOLVED_LIMIT = 300;

    /** @return list<array<string, mixed>> */
    public static function rows(): array
    {
        return OpsCache::remember('incidents:list', fn (): array => [
            ...self::query(IncidentStatus::Open)->get()->map(IncidentPresenter::row(...))->all(),
            ...self::query(IncidentStatus::Resolved)->limit(self::RESOLVED_LIMIT)->get()->map(IncidentPresenter::row(...))->all(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{open: int, resolved: int, critical: int}
     */
    public static function counts(array $rows): array
    {
        return [
            'open' => count(array_filter($rows, fn (array $row): bool => $row['is_open'] === true)),
            'resolved' => count(array_filter($rows, fn (array $row): bool => $row['is_open'] === false)),
            'critical' => count(array_filter($rows, fn (array $row): bool => $row['is_open'] === true && $row['severity'] === 'critical')),
        ];
    }

    /** @return Builder<Incident> */
    private static function query(IncidentStatus $status): Builder
    {
        return Incident::query()->with('rule')->where('status', $status->value)->latest('opened_at')->latest('id');
    }
}
