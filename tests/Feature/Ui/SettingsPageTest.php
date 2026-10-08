<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Phattarachai\OpsLaravel\Alerts\Channels\IncidentMail;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

const SLACK_WEBHOOK = 'https://hooks.slack.test/T000/B000/secret-token';

/**
 * @param  array<string, mixed>  $overrides  dotted setting key => value
 * @return array<string, mixed>
 */
function settingsPayload(array $overrides = []): array
{
    $payload = [
        'channels' => [
            'mail' => ['enabled' => true, 'recipients' => ['ops@example.test'], 'mailer' => null, 'from_address' => 'alerts@example.test', 'from_name' => 'Ops'],
            'slack' => ['enabled' => false, 'webhook_url' => '', 'clear_webhook' => false],
        ],
        'recorders' => [
            'requests' => ['enabled' => true, 'paths' => ['*'], 'ignore' => ['up']],
            'outgoing' => ['enabled' => true, 'ignore_hosts' => []],
            'jobs' => ['enabled' => true, 'ignore' => []],
            'schedule' => ['enabled' => true],
            'security' => ['enabled' => true, 'statuses' => [401, 403, 404, 419, 429]],
        ],
        'signals' => ['user_route_floor' => 30],
        'retention' => ['minute_days' => 7, 'hour_days' => 365, 'user_days' => 30, 'runs_days' => 30, 'signal_days' => 7],
    ];

    foreach ($overrides as $key => $value) {
        Arr::set($payload, $key, $value);
    }

    return $payload;
}

beforeEach(function (): void {
    Ops::auth(fn (Request $request): bool => true);
});

it('shows the current settings without the Slack webhook', function (): void {
    app(OpsSettings::class)->set('channels.slack.webhook_url', SLACK_WEBHOOK);

    $response = $this->get(route('ops.settings'));

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'settings')
            ->where('settings.channels.mail.enabled', true)
            ->where('settings.recorders.security.statuses', [401, 403, 404, 419, 429])
            ->where('settings.retention.hour_days', 365)
            ->missing('settings.channels.slack.webhook_url')
            ->where('slack_webhook_configured', true)
            ->where('mailers', array_keys(config('mail.mailers')))
            ->where('test_urls.slack', route('ops.settings.test', ['channel' => 'slack'])));

    expect($response->getContent())->not->toContain('hooks.slack.test');
});

it('stores the settings and keeps config defaults unstored', function (): void {
    $this->put(route('ops.settings.update'), settingsPayload([
        'channels.slack.enabled' => true,
        'channels.slack.webhook_url' => SLACK_WEBHOOK,
        'recorders.outgoing.ignore_hosts' => ['metadata.internal', ' ', 'metadata.internal'],
        'recorders.security.statuses' => [429, '403'],
        'retention.runs_days' => 14,
    ]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('ops.flash.message', 'Settings saved');

    $settings = app(OpsSettings::class);

    expect($settings->list('channels.mail.recipients'))->toBe(['ops@example.test'])
        ->and($settings->bool('channels.slack.enabled'))->toBeTrue()
        ->and($settings->get('channels.slack.webhook_url'))->toBe(SLACK_WEBHOOK)
        ->and($settings->list('recorders.outgoing.ignore_hosts'))->toBe(['metadata.internal'])
        ->and($settings->get('recorders.security.statuses'))->toBe([403, 429])
        ->and($settings->int('retention.runs_days'))->toBe(14)
        ->and($settings->has('retention.hour_days'))->toBeFalse();
});

it('keeps the stored webhook when the field is left empty, and forgets it on clear', function (): void {
    app(OpsSettings::class)->set('channels.slack.webhook_url', SLACK_WEBHOOK);

    $this->put(route('ops.settings.update'), settingsPayload(['channels.slack.enabled' => true]))->assertSessionHasNoErrors();
    expect(app(OpsSettings::class)->get('channels.slack.webhook_url'))->toBe(SLACK_WEBHOOK);

    $this->put(route('ops.settings.update'), settingsPayload(['channels.slack.clear_webhook' => true]))->assertSessionHasNoErrors();
    expect(app(OpsSettings::class)->has('channels.slack.webhook_url'))->toBeFalse();
});

it('validates the settings', function (array $overrides, string $error): void {
    $this->put(route('ops.settings.update'), settingsPayload($overrides))->assertSessionHasErrors($error);
})->with([
    'slack enabled without a webhook' => [['channels.slack.enabled' => true], 'channels.slack.webhook_url'],
    'webhook over http' => [['channels.slack.webhook_url' => 'http://hooks.slack.test/x'], 'channels.slack.webhook_url'],
    'mail enabled without recipients' => [['channels.mail.recipients' => []], 'channels.mail.recipients'],
    'bad recipient' => [['channels.mail.recipients' => ['not-an-email']], 'channels.mail.recipients.0'],
    'unknown mailer' => [['channels.mail.mailer' => 'pigeon'], 'channels.mail.mailer'],
    'status out of range' => [['recorders.security.statuses' => [42]], 'recorders.security.statuses.0'],
    'zero retention' => [['retention.minute_days' => 0], 'retention.minute_days'],
]);

it('sends a test mail and flashes success', function (): void {
    Mail::fake();
    app(OpsSettings::class)->set('channels.mail.recipients', ['ops@example.test']);

    $this->post(route('ops.settings.test', ['channel' => 'mail']))
        ->assertRedirect()
        ->assertSessionHas('ops.flash.tone', 'success')
        ->assertSessionHas('ops.flash.message', 'Test notification sent through mail');

    Mail::assertSent(IncidentMail::class);
});

it('flashes the delivery error when a test fails, with the webhook masked', function (): void {
    app(OpsSettings::class)->set('channels.slack.webhook_url', SLACK_WEBHOOK);
    Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host for '.SLACK_WEBHOOK));

    $response = $this->post(route('ops.settings.test', ['channel' => 'slack']))
        ->assertSessionHas('ops.flash.tone', 'error');

    expect(session('ops.flash.message'))->toStartWith('Test through slack failed: cURL error 6')
        ->not->toContain('secret-token');

    $response->assertRedirect();
});

it('reports a channel that is not configured', function (): void {
    $this->post(route('ops.settings.test', ['channel' => 'mail']))
        ->assertSessionHas('ops.flash.message', 'Test through mail failed: No mail recipients are configured.');
});

it('returns 404 for an unknown channel', function (): void {
    $this->post(route('ops.settings.test', ['channel' => 'pager']))->assertNotFound();
});
