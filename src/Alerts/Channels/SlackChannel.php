<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Alerts\Channels;

use Illuminate\Support\Facades\Http;
use Phattarachai\OpsCore\Alerts\Contracts\IncidentChannel;
use Phattarachai\OpsCore\Alerts\IncidentNotice;
use Phattarachai\OpsCore\Alerts\NoticeKind;
use Phattarachai\OpsCore\Alerts\Severity;
use Phattarachai\OpsLaravel\Alerts\NoticeText;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use RuntimeException;

final readonly class SlackChannel implements IncidentChannel
{
    public const string NAME = 'slack';

    public function __construct(private OpsSettings $settings) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function send(IncidentNotice $notice): void
    {
        $webhook = $this->settings->get('channels.slack.webhook_url');

        if (! is_string($webhook) || ! str_starts_with($webhook, 'https://')) {
            throw new RuntimeException('No Slack webhook URL is configured.');
        }

        Http::timeout(10)->post($webhook, $this->payload($notice))->throw();
    }

    /** @return array<string, mixed> */
    public function payload(IncidentNotice $notice): array
    {
        $text = new NoticeText($notice);
        $body = implode("\n", $text->lines());
        $link = $text->url() === null ? '' : "\n<{$text->url()}|{$text->openLabel()}>";

        return [
            'text' => "{$this->emoji($notice)} {$text->subject()}",
            'blocks' => [
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "{$this->emoji($notice)} *{$text->subject()}*\n{$body}{$link}"]],
            ],
        ];
    }

    private function emoji(IncidentNotice $notice): string
    {
        return match (true) {
            $notice->kind === NoticeKind::Resolved => ':white_check_mark:',
            $notice->kind === NoticeKind::Test => ':test_tube:',
            $notice->incident->severity === Severity::Critical => ':rotating_light:',
            default => ':warning:',
        };
    }
}
