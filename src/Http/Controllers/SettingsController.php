<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Inertia\Response;
use Phattarachai\OpsLaravel\Alerts\ChannelRegistry;
use Phattarachai\OpsLaravel\Alerts\Channels\MailChannel;
use Phattarachai\OpsLaravel\Alerts\Channels\SlackChannel;
use Phattarachai\OpsLaravel\Alerts\ChannelTester;
use Phattarachai\OpsLaravel\Alerts\DeliveryError;
use Phattarachai\OpsLaravel\Http\Requests\SettingsRequest;
use Phattarachai\OpsLaravel\Models\NotificationSent;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\UiPage;

/**
 * Channels and recorder settings, stored through OpsSettings. The Slack webhook is a secret:
 * the page only learns whether one is stored, and an empty field keeps it.
 */
final class SettingsController
{
    private const array CHANNELS = [MailChannel::NAME, SlackChannel::NAME];

    public function __construct(private readonly OpsSettings $settings) {}

    public function index(): Response
    {
        return UiPage::render('settings', [
            'settings' => $this->values(),
            'slack_webhook_configured' => $this->hasWebhook(),
            'mailers' => array_keys((array) config('mail.mailers', [])),
            'default_mailer' => config('mail.default'),
            'deliveries' => array_combine(self::CHANNELS, array_map($this->lastDelivery(...), self::CHANNELS)),
            'update_url' => route('ops.settings.update'),
            'test_urls' => array_combine(self::CHANNELS, array_map(fn (string $channel): string => route('ops.settings.test', ['channel' => $channel]), self::CHANNELS)),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        foreach ($request->values() as $key => $value) {
            $this->store($key, $value);
        }

        $this->storeWebhook($request);
        UiPage::toast('Settings saved');

        return back();
    }

    public function test(string $channel, ChannelRegistry $channels, ChannelTester $tester): RedirectResponse
    {
        abort_unless(in_array($channel, $channels->names(), true), 404);

        $tester->test($channel)
            ? UiPage::toast('Test notification sent through :channel', ['channel' => $channel])
            : UiPage::toast('Test through :channel failed: :error', ['channel' => $channel, 'error' => $this->lastError($channel)], 'error');

        return back();
    }

    /** @return array<string, mixed> */
    private function values(): array
    {
        $values = [];

        foreach (SettingsRequest::FIELDS as $key => $type) {
            Arr::set($values, $key, match ($type) {
                'bool' => $this->settings->bool($key),
                'int' => $this->settings->int($key),
                'string' => is_string($this->settings->get($key)) ? $this->settings->get($key) : null,
                'list', 'emails' => $this->settings->list($key),
                'statuses' => array_values(array_map(intval(...), Arr::wrap($this->settings->get($key, [])))),
            });
        }

        return $values;
    }

    private function store(string $key, mixed $value): void
    {
        if ($value === config("ops.{$key}")) {
            $this->forget($key);

            return;
        }

        if (! $this->settings->has($key) || $this->settings->get($key) !== $value) {
            $this->settings->set($key, $value);
        }
    }

    private function forget(string $key): void
    {
        if ($this->settings->has($key)) {
            $this->settings->forget($key);
        }
    }

    private function storeWebhook(SettingsRequest $request): void
    {
        $webhook = $request->webhook();

        if ($webhook !== null) {
            $this->settings->set(SettingsRequest::WEBHOOK, $webhook);
        }

        if ($request->clearsWebhook()) {
            $this->forget(SettingsRequest::WEBHOOK);
        }
    }

    private function hasWebhook(): bool
    {
        $webhook = $this->settings->get(SettingsRequest::WEBHOOK);

        return is_string($webhook) && $webhook !== '';
    }

    /** @return array{at: ?string, delivered: bool, kind: string, error: ?string}|null */
    private function lastDelivery(string $channel): ?array
    {
        $sent = NotificationSent::query()->where('channel', $channel)->latest('id')->first();

        return $sent === null ? null : [
            'at' => Format::date($sent->sent_at),
            'delivered' => $sent->delivered,
            'kind' => $sent->kind,
            'error' => DeliveryError::redact($sent->error),
        ];
    }

    private function lastError(string $channel): string
    {
        $error = NotificationSent::query()->where('channel', $channel)->where('delivered', false)->latest('id')->value('error');

        return (string) (DeliveryError::redact(is_string($error) ? $error : null) ?? 'unknown error');
    }
}
