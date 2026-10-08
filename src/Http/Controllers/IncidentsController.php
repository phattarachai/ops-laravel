<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Phattarachai\OpsCore\Alerts\IncidentStatus;
use Phattarachai\OpsLaravel\Models\Incident;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\Reports\Incidents\IncidentDetail;
use Phattarachai\OpsLaravel\Ui\Reports\Incidents\IncidentList;
use Phattarachai\OpsLaravel\Ui\UiPage;

final class IncidentsController
{
    public function index(): Response
    {
        $incidents = IncidentList::rows();

        return UiPage::render('incidents', [
            'incidents' => $incidents,
            'counts' => IncidentList::counts($incidents),
        ]);
    }

    public function show(string $token): Response
    {
        $incident = Incident::query()->with('rule')->findOrFail((int) $token);

        return UiPage::render('incidents.show', [
            ...new IncidentDetail($incident)->toProps(),
            'resolve_url' => route('ops.incidents.resolve', ['incident' => $incident->id]),
            'back_url' => route('ops.incidents'),
        ]);
    }

    public function resolve(int $incident): RedirectResponse
    {
        $model = Incident::query()->findOrFail($incident);

        if ($model->status === IncidentStatus::Open) {
            $model->update(['status' => IncidentStatus::Resolved, 'resolved_at' => now()]);
            OpsCache::bust();
        }

        $this->toast('Incident resolved');

        return back();
    }

    /** @param  array<string, string|int>  $vars */
    private function toast(string $text, array $vars = [], string $tone = 'success'): void
    {
        session()->flash('ops.flash', [
            'message' => strtr($text, collect($vars)->mapWithKeys(fn (string|int $value, string $name): array => [":{$name}" => (string) $value])->all()),
            'tone' => $tone,
            'text' => $text,
            'vars' => $vars,
        ]);
    }
}
