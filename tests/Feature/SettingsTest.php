<?php

declare(strict_types=1);

use Phattarachai\OpsLaravel\Models\Setting;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

it('falls back to the config default until a value is stored', function (): void {
    $settings = app(OpsSettings::class);

    expect($settings->int('retention.runs_days'))->toBe(30);

    $settings->set('retention.runs_days', 14);

    expect($settings->int('retention.runs_days'))->toBe(14)
        ->and($settings->has('retention.runs_days'))->toBeTrue();
});

it('stores secret settings encrypted', function (): void {
    app(OpsSettings::class)->set('channels.slack.webhook_url', 'https://hooks.slack.test/T000/B000/abc');

    $stored = Setting::query()->where('key', 'channels.slack.webhook_url')->value('value');

    expect($stored)->not->toContain('hooks.slack.test')
        ->and(app(OpsSettings::class)->get('channels.slack.webhook_url'))->toBe('https://hooks.slack.test/T000/B000/abc');
});
