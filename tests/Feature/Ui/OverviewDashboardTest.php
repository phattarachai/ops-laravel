<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;
use Phattarachai\OpsCore\Alerts\IncidentStatus;
use Phattarachai\OpsCore\Alerts\RuleType;
use Phattarachai\OpsCore\Alerts\Severity;
use Phattarachai\OpsLaravel\Enums\JobRunStatus;
use Phattarachai\OpsLaravel\Models\Incident;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Ui\TimeRange;

beforeEach(function (): void {
    Ops::auth(fn (Request $request): bool => true);
    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:15:20'));
});

it('lists an area block per part and the failing items worst first', function (): void {
    $rule = Rule::query()->create(['name' => '403 burst', 'type' => RuleType::Threshold, 'signal' => 'http.403', 'threshold' => 20]);
    Incident::query()->create(['rule_id' => $rule->id, 'key' => 'ip:1.2.3.4', 'status' => IncidentStatus::Open, 'severity' => Severity::Critical, 'opened_at' => now()->subMinutes(5), 'last_breached_at' => now(), 'peak_value' => 30, 'last_value' => 30]);
    JobRun::query()->create(['job_class' => 'App\Jobs\SyncThing', 'status' => JobRunStatus::Failed, 'started_at' => now()->subHour(), 'attempts' => 1]);

    Ops::overviewBlocks(fn (TimeRange $range): array => [[
        'key' => 'mail', 'title' => 'Mail', 'url' => '/mail', 'health' => ['tone' => 'success', 'label' => 'Healthy'],
        'stats' => [], 'chart' => ['span' => $range->label, 'series' => []], 'attention' => [],
    ]]);

    $this->get(route('ops.overview'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'overview')
            ->where('areas.0.key', 'incidents')
            ->where('areas.0.health.tone', 'danger')
            ->where('areas.6.key', 'mail')
            ->where('attention.0.area', 'Incidents')
            ->where('attention.1.name', 'SyncThing')
            ->etc());
});
