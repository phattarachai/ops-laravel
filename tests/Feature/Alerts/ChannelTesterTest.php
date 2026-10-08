<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Phattarachai\OpsLaravel\Alerts\Channels\IncidentMail;
use Phattarachai\OpsLaravel\Alerts\ChannelTester;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

it('sends a test mail from the configured sender', function (): void {
    Mail::fake();
    app(OpsSettings::class)->set('channels.mail.recipients', ['ops@example.test']);
    app(OpsSettings::class)->set('channels.mail.from_address', 'alerts@example.test');

    expect(app(ChannelTester::class)->test('mail'))->toBeTrue();

    Mail::assertSent(IncidentMail::class, fn (IncidentMail $mail): bool => $mail->envelope()->from?->address === 'alerts@example.test');
});

it('reports a channel that is not configured', function (): void {
    expect(app(ChannelTester::class)->test('slack'))->toBeFalse()
        ->and(app(ChannelTester::class)->test('mail'))->toBeFalse();
});
