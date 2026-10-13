<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;
use Phattarachai\OpsCore\Alerts\ProbeLevel;
use Phattarachai\OpsCore\Alerts\RuleType;
use Phattarachai\OpsCore\Alerts\Severity;
use Phattarachai\OpsLaravel\Alerts\DefaultRules;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Ops;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rulePayload(array $overrides = []): array
{
    return [
        'name' => 'Stripe failing',
        'type' => 'threshold',
        'signal' => 'outgoing.failed',
        'severity' => 'warning',
        'threshold' => 5,
        'window_seconds' => 300,
        'distinct_field' => null,
        'denominator_signal' => null,
        'min_samples' => null,
        'min_level' => null,
        'keys' => ['api.stripe.test'],
        'environments' => ['production'],
        'channels' => ['mail'],
        'cooldown_seconds' => 900,
        'resolve_after_seconds' => 300,
        'active' => true,
        'notify_on_resolve' => true,
        ...$overrides,
    ];
}

function storedRule(): Rule
{
    return Rule::query()->create(['name' => 'Job failures', 'type' => RuleType::Threshold, 'signal' => 'job.failed', 'threshold' => 3, 'window_seconds' => 900, 'severity' => Severity::Warning]);
}

beforeEach(function (): void {
    Ops::auth(fn (Request $request): bool => true);
    $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00'));
});

it('lists the rules with the form options', function (): void {
    $rule = storedRule();

    $this->get(route('ops.rules', ['rule' => $rule->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'rules')
            ->has('rules', 1)
            ->where('rules.0.name', 'Job failures')
            ->where('rules.0.type', 'threshold')
            ->where('rules.0.is_muted', false)
            ->where('rules.0.update_url', route('ops.rules.update', ['rule' => $rule->id]))
            ->where('options.types', ['threshold', 'rate', 'absence', 'state'])
            ->where('options.channels', ['mail', 'slack'])
            ->where('options.missing_defaults', count(DefaultRules::definitions()))
            ->where('options.signals.0.value', 'http.user_route')
            ->where('focus_rule_id', $rule->id));
});

it('creates a threshold rule', function (): void {
    $this->post(route('ops.rules.store'), rulePayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('ops.flash.message', 'Rule "Stripe failing" created');

    $rule = Rule::query()->sole();

    expect($rule->type)->toBe(RuleType::Threshold)
        ->and($rule->threshold)->toBe(5.0)
        ->and($rule->keys)->toBe(['api.stripe.test'])
        ->and($rule->channels)->toBe(['mail'])
        ->and($rule->muted_until)->toBeNull();
});

it('keeps only the fields a rate rule uses', function (): void {
    $this->post(route('ops.rules.store'), rulePayload(['type' => 'rate', 'threshold' => 25, 'denominator_signal' => 'outgoing.call', 'min_samples' => 20, 'distinct_field' => 'ip', 'min_level' => 'critical']))
        ->assertSessionHasNoErrors();

    $rule = Rule::query()->sole();

    expect($rule->denominator_signal)->toBe('outgoing.call')
        ->and($rule->min_samples)->toBe(20)
        ->and($rule->distinct_field)->toBeNull()
        ->and($rule->min_level)->toBe(ProbeLevel::Warning);
});

it('validates the fields each rule type needs', function (array $payload, array $errors): void {
    $this->post(route('ops.rules.store'), rulePayload($payload))->assertSessionHasErrors($errors);

    expect(Rule::query()->count())->toBe(0);
})->with([
    'rate without a denominator' => [['type' => 'rate', 'threshold' => 10], ['denominator_signal']],
    'rate over 100 %' => [['type' => 'rate', 'threshold' => 150, 'denominator_signal' => 'outgoing.call'], ['threshold']],
    'absence shorter than a minute' => [['type' => 'absence', 'threshold' => 30], ['threshold']],
    'state without a level' => [['type' => 'state', 'threshold' => null], ['min_level']],
    'unknown type and channel' => [['type' => 'nope', 'channels' => ['pager']], ['type', 'channels.0']],
    'threshold without a window' => [['window_seconds' => null], ['window_seconds']],
]);

it('updates a rule', function (): void {
    $rule = storedRule();

    $this->put(route('ops.rules.update', ['rule' => $rule->id]), rulePayload(['name' => 'Renamed', 'type' => 'state', 'min_level' => 'critical', 'threshold' => null]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('ops.flash.message', 'Rule "Renamed" saved');

    $rule->refresh();

    expect($rule->name)->toBe('Renamed')
        ->and($rule->type)->toBe(RuleType::State)
        ->and($rule->min_level)->toBe(ProbeLevel::Critical)
        ->and($rule->threshold)->toBe(0.0);
});

it('turns warning reminders off, and keeps the setting when a save leaves it out', function (): void {
    $rule = storedRule();

    $this->put(route('ops.rules.update', ['rule' => $rule->id]), rulePayload(['remind_warnings' => false]))->assertSessionHasNoErrors();

    expect($rule->refresh()->remind_warnings)->toBeFalse();

    $this->put(route('ops.rules.update', ['rule' => $rule->id]), rulePayload())->assertSessionHasNoErrors();

    expect($rule->refresh()->remind_warnings)->toBeFalse()
        ->and($rule->toSpec()->remindWarnings)->toBeFalse();
});

it('mutes a rule for an hour, keeps the mute on a plain save, then clears it', function (): void {
    $rule = storedRule();
    $url = route('ops.rules.update', ['rule' => $rule->id]);

    $this->put($url, rulePayload(['mute' => '1h']))->assertSessionHasNoErrors();
    expect($rule->refresh()->muted_until?->toDateTimeString())->toBe('2026-10-08 13:00:00');

    $this->put($url, rulePayload(['active' => false]))->assertSessionHasNoErrors();
    expect($rule->refresh()->muted_until?->toDateTimeString())->toBe('2026-10-08 13:00:00')
        ->and($rule->active)->toBeFalse();

    $this->put($url, rulePayload(['mute' => 'clear']))->assertSessionHas('ops.flash.message', 'Rule "Stripe failing" unmuted');
    expect($rule->refresh()->muted_until)->toBeNull();
});

it('deletes a rule', function (): void {
    $rule = storedRule();

    $this->delete(route('ops.rules.destroy', ['rule' => $rule->id]))
        ->assertRedirect()
        ->assertSessionHas('ops.flash.message', 'Rule "Job failures" deleted');

    expect(Rule::query()->count())->toBe(0);
});

it('adds the default rules once', function (): void {
    $this->post(route('ops.rules.defaults'))->assertSessionHas('ops.flash.message', count(DefaultRules::definitions()).' default rules added');
    $this->post(route('ops.rules.defaults'))->assertSessionHas('ops.flash.tone', 'info');

    expect(Rule::query()->count())->toBe(count(DefaultRules::definitions()));
});
