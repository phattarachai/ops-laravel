<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Alerts\Channels;

use Illuminate\Support\Facades\Mail;
use Phattarachai\OpsCore\Alerts\Contracts\IncidentChannel;
use Phattarachai\OpsCore\Alerts\IncidentNotice;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use RuntimeException;

final readonly class MailChannel implements IncidentChannel
{
    public const string NAME = 'mail';

    public function __construct(private OpsSettings $settings) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function send(IncidentNotice $notice): void
    {
        $recipients = $this->settings->list('channels.mail.recipients');

        if ($recipients === []) {
            throw new RuntimeException('No mail recipients are configured.');
        }

        Mail::mailer($this->mailer())->to($recipients)->send(new IncidentMail(
            $notice,
            $this->stringSetting('channels.mail.from_address'),
            $this->stringSetting('channels.mail.from_name'),
        ));
    }

    private function mailer(): ?string
    {
        $mailer = $this->stringSetting('channels.mail.mailer');

        return $mailer !== null && array_key_exists($mailer, (array) config('mail.mailers', [])) ? $mailer : null;
    }

    private function stringSetting(string $key): ?string
    {
        $value = $this->settings->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
