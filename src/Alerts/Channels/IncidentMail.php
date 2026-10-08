<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Alerts\Channels;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Phattarachai\OpsCore\Alerts\IncidentNotice;
use Phattarachai\OpsCore\Alerts\NoticeKind;
use Phattarachai\OpsCore\Alerts\Severity;
use Phattarachai\OpsLaravel\Alerts\NoticeText;

final class IncidentMail extends Mailable
{
    public function __construct(
        public readonly IncidentNotice $notice,
        public readonly ?string $fromAddress = null,
        public readonly ?string $fromName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromAddress === null || $this->fromAddress === '' ? null : new Address($this->fromAddress, $this->fromName),
            subject: new NoticeText($this->notice)->subject(),
        );
    }

    public function content(): Content
    {
        $text = new NoticeText($this->notice);

        return new Content(view: 'ops::mail.incident', with: [
            'locale' => config('ops.locale') ?? app()->getLocale(),
            'kind' => $text->kind(),
            'subject' => $text->subject(),
            'lines' => $text->lines(),
            'url' => $text->url(),
            'openLabel' => $text->openLabel(),
            'tone' => $this->tone(),
        ]);
    }

    private function tone(): string
    {
        return match (true) {
            $this->notice->kind === NoticeKind::Resolved => '#16a34a',
            $this->notice->kind === NoticeKind::Test => '#2a78d6',
            $this->notice->incident->severity === Severity::Critical => '#dc2626',
            default => '#d97706',
        };
    }
}
