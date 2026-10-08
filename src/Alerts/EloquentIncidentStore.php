<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Alerts;

use Phattarachai\OpsCore\Alerts\Contracts\IncidentStore;
use Phattarachai\OpsCore\Alerts\Incident as CoreIncident;
use Phattarachai\OpsCore\Alerts\IncidentStatus;
use Phattarachai\OpsLaravel\Models\Incident;

final class EloquentIncidentStore implements IncidentStore
{
    public function openFor(int|string $ruleId): array
    {
        return Incident::query()
            ->where('rule_id', $ruleId)
            ->where('status', IncidentStatus::Open)
            ->get()
            ->map(fn (Incident $incident): CoreIncident => $incident->toCore())
            ->values()
            ->all();
    }

    public function save(CoreIncident $incident): CoreIncident
    {
        $model = $incident->id === null ? new Incident : Incident::query()->findOrNew($incident->id);
        $model->fill(Incident::attributesFrom($incident))->save();

        return $incident->withId($model->id);
    }
}
