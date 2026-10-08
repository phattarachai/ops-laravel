<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;
use Phattarachai\OpsCore\Alerts\IncidentStatus;
use Phattarachai\OpsCore\Alerts\RuleType;
use Phattarachai\OpsCore\Alerts\Severity;
use Phattarachai\OpsLaravel\Models\Incident;
use Phattarachai\OpsLaravel\Models\NotificationSent;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

function incidentRule(): Rule
{
    return Rule::query()->create(['name' => 'Failed logins', 'type' => RuleType::Threshold, 'signal' => 'auth.failed', 'threshold' => 20, 'window_seconds' => 600]);
}

/** @param  array<string, mixed>  $attributes */
function incidentFor(Rule $rule, string $openedAt, array $attributes = []): Incident
{
    return Incident::query()->create([
        'rule_id' => $rule->id,
        'key' => 'ip:203.0.113.9',
        'status' => IncidentStatus::Open,
        'severity' => Severity::Warning,
        'opened_at' => CarbonImmutable::parse($openedAt),
        'last_breached_at' => CarbonImmutable::parse($openedAt)->addMinutes(5),
        'peak_value' => 42,
        'last_value' => 30,
        'notifications' => 1,
        'context' => ['count' => 42, 'window_seconds' => 600],
        ...$attributes,
    ]);
}

beforeEach(function (): void {
    Ops::auth(fn (Request $request): bool => true);
    $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00'));
});

it('lists open incidents first, then resolved ones, newest first', function (): void {
    $rule = incidentRule();
    $resolved = incidentFor($rule, '2026-10-08 11:00:00', ['status' => IncidentStatus::Resolved, 'resolved_at' => CarbonImmutable::parse('2026-10-08 11:30:00'), 'severity' => Severity::Critical]);
    $older = incidentFor($rule, '2026-10-07 09:00:00');
    $newer = incidentFor($rule, '2026-10-08 10:00:00', ['severity' => Severity::Critical]);

    $this->get(route('ops.incidents'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'incidents')
            ->has('incidents', 3)
            ->where('incidents.0.id', $newer->id)
            ->where('incidents.1.id', $older->id)
            ->where('incidents.2.id', $resolved->id)
            ->where('incidents.0.rule_name', 'Failed logins')
            ->where('incidents.0.severity', 'critical')
            ->where('incidents.0.peak', '42')
            ->where('incidents.0.notifications', 1)
            ->where('incidents.2.duration', '30m')
            ->where('counts', ['open' => 2, 'resolved' => 1, 'critical' => 1]));
});

it('shows an incident with its timeline, context and rule, masking URLs in delivery errors', function (): void {
    app(OpsSettings::class)->set('channels.slack.webhook_url', 'https://hooks.slack.test/T000/B000/secret-token');
    $incident = incidentFor(incidentRule(), '2026-10-08 10:00:00');
    NotificationSent::query()->create(['incident_id' => $incident->id, 'rule_id' => $incident->rule_id, 'channel' => 'mail', 'kind' => 'opened', 'delivered' => true, 'sent_at' => CarbonImmutable::parse('2026-10-08 10:00:30')]);
    NotificationSent::query()->create(['incident_id' => $incident->id, 'rule_id' => $incident->rule_id, 'channel' => 'slack', 'kind' => 'opened', 'delivered' => false, 'error' => 'cURL error 6: Could not resolve host for https://hooks.slack.test/T000/B000/secret-token', 'sent_at' => CarbonImmutable::parse('2026-10-08 10:00:31')]);

    $response = $this->get(route('ops.incidents.show', ['token' => $incident->id]));

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'incidents.show')
            ->where('incident.id', $incident->id)
            ->where('incident.is_open', true)
            ->where('rule.name', 'Failed logins')
            ->where('rule.url', route('ops.rules', ['rule' => $incident->rule_id]))
            ->where('context', ['count' => 42, 'window_seconds' => 600])
            ->has('timeline', 4)
            ->where('timeline.0.type', 'opened')
            ->where('timeline.1.channel', 'mail')
            ->where('timeline.2.delivered', false)
            ->where('timeline.2.error', 'cURL error 6: Could not resolve host for ••••')
            ->where('timeline.3.type', 'breach')
            ->where('resolve_url', route('ops.incidents.resolve', ['incident' => $incident->id])));

    expect($response->getContent())->not->toContain('secret-token');
});

it('returns 404 for an unknown incident', function (): void {
    $this->get(route('ops.incidents.show', ['token' => 999]))->assertNotFound();
});

it('resolves an open incident now and flashes a toast', function (): void {
    $incident = incidentFor(incidentRule(), '2026-10-08 10:00:00');

    $this->from(route('ops.incidents.show', ['token' => $incident->id]))
        ->post(route('ops.incidents.resolve', ['incident' => $incident->id]))
        ->assertRedirect(route('ops.incidents.show', ['token' => $incident->id]))
        ->assertSessionHas('ops.flash.message', 'Incident resolved')
        ->assertSessionHas('ops.flash.tone', 'success');

    $incident->refresh();

    expect($incident->status)->toBe(IncidentStatus::Resolved)
        ->and($incident->resolved_at?->toDateTimeString())->toBe('2026-10-08 12:00:00');
});
