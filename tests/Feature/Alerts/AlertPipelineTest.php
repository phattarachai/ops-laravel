<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Phattarachai\OpsCore\Alerts\IncidentStatus;
use Phattarachai\OpsCore\Alerts\ProbeLevel;
use Phattarachai\OpsCore\Alerts\RuleType;
use Phattarachai\OpsLaravel\Alerts\Channels\IncidentMail;
use Phattarachai\OpsLaravel\Alerts\DefaultRules;
use Phattarachai\OpsLaravel\Models\Incident;
use Phattarachai\OpsLaravel\Models\NotificationSent;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use Phattarachai\OpsLaravel\Tests\Fixtures\RecordedJob;

beforeEach(function (): void {
    Mail::fake();
    Route::get('api/secret', fn () => abort(403));
    app(OpsSettings::class)->set('channels.mail.recipients', ['ops@example.test']);
    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:15:10'));
});

function burstRule(array $overrides = []): Rule
{
    return Rule::query()->create([
        'name' => '403 burst',
        'type' => RuleType::Threshold,
        'signal' => 'http.403',
        'threshold' => 3,
        'window_seconds' => 300,
        ...$overrides,
    ]);
}

it('turns a 403 burst into one incident and one mail', function (): void {
    burstRule();

    foreach (range(1, 3) as $ignored) {
        $this->getJson('api/secret', ['REMOTE_ADDR' => '203.0.113.9'])->assertForbidden();
    }

    rollupAt('2026-10-08 10:16:02');
    $this->artisan('ops:alerts')->assertSuccessful();
    $this->artisan('ops:alerts')->assertSuccessful();

    $incident = Incident::query()->sole();

    expect($incident)
        ->key->toBe('ip:203.0.113.9')
        ->status->toBe(IncidentStatus::Open)
        ->last_value->toBe(3.0)
        ->notifications->toBe(1);

    Mail::assertSent(IncidentMail::class, fn (IncidentMail $mail): bool => $mail->hasTo('ops@example.test') && str_contains($mail->envelope()->subject, 'ip:203.0.113.9'));
    Mail::assertSentCount(1);
    expect(NotificationSent::query()->sole())->channel->toBe('mail')->delivered->toBeTrue();
});

it('resolves the incident once the rule stays clear and says so', function (): void {
    burstRule(['resolve_after_seconds' => 300]);

    foreach (range(1, 3) as $ignored) {
        $this->getJson('api/secret');
    }

    rollupAt('2026-10-08 10:16:02');
    $this->artisan('ops:alerts');

    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:30:02'));
    $this->artisan('ops:alerts');

    expect(Incident::query()->sole()->status)->toBe(IncidentStatus::Resolved);
    Mail::assertSentCount(2);
});

it('fires on built-in job failures and on host probes', function (): void {
    Rule::query()->create(['name' => 'jobs', 'type' => RuleType::Threshold, 'signal' => 'job.failed', 'threshold' => 1, 'window_seconds' => 900]);
    Rule::query()->create(['name' => 'vpn', 'type' => RuleType::State, 'signal' => 'network']);

    expect(fn () => RecordedJob::dispatchSync(fail: true))->toThrow(RuntimeException::class);
    Ops::probe('network', 'vpn', ProbeLevel::Critical, message: 'down');

    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:16:02'));
    $this->artisan('ops:alerts');

    expect(Incident::query()->orderBy('id')->pluck('key')->all())->toBe([RecordedJob::class, 'vpn']);
});

it('skips rules outside their environments and sends nothing when muted', function (): void {
    burstRule(['environments' => ['production']]);
    burstRule(['name' => 'muted', 'muted_until' => now()->addHour()]);

    foreach (range(1, 3) as $ignored) {
        $this->getJson('api/secret');
    }

    rollupAt('2026-10-08 10:16:02');
    $this->artisan('ops:alerts');

    expect(Incident::query()->count())->toBe(1);
    Mail::assertNothingSent();
});

it('posts to Slack when the channel is enabled and logs a failed delivery', function (): void {
    Http::fake(['hooks.slack.test/*' => Http::response('no_service', 404)]);
    app(OpsSettings::class)->set('channels.slack.enabled', true);
    app(OpsSettings::class)->set('channels.slack.webhook_url', 'https://hooks.slack.test/services/T/B/x');
    burstRule(['channels' => ['slack']]);

    foreach (range(1, 3) as $ignored) {
        $this->getJson('api/secret');
    }

    rollupAt('2026-10-08 10:16:02');
    $this->artisan('ops:alerts');

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://hooks.slack.test/') && str_contains((string) $request['text'], '403 burst'));
    Mail::assertNothingSent();
    expect(NotificationSent::query()->sole())->channel->toBe('slack')->delivered->toBeFalse();
});

it('seeds the default rules once without overwriting edits', function (): void {
    expect(DefaultRules::seed())->toBe(count(DefaultRules::definitions()));

    Rule::query()->where('slug', 'http-403-burst')->update(['threshold' => 50]);

    expect(DefaultRules::seed())->toBe(0)
        ->and(Rule::query()->where('slug', 'http-403-burst')->value('threshold'))->toBe(50.0);
});
