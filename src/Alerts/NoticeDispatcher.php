<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Alerts;

use Phattarachai\OpsCore\Alerts\IncidentNotice;
use Phattarachai\OpsLaravel\Models\NotificationSent;
use Throwable;

/**
 * Sends synchronously from the scheduler, so a stalled queue cannot swallow the alert about it.
 * Every attempt, delivered or not, is written to ops_notifications_sent.
 */
final readonly class NoticeDispatcher
{
    public function __construct(private ChannelRegistry $channels) {}

    /** @param  list<IncidentNotice>  $notices */
    public function dispatch(array $notices): int
    {
        $sent = 0;

        foreach ($notices as $notice) {
            foreach ($this->channels->enabledFor($notice->rule->channels) as $name) {
                $sent += $this->send($name, $notice) ? 1 : 0;
            }
        }

        return $sent;
    }

    public function send(string $name, IncidentNotice $notice): bool
    {
        $error = null;

        try {
            $this->channels->get($name)?->send($notice);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            report($exception);
        }

        $this->log($name, $notice, $error);

        return $error === null;
    }

    private function log(string $name, IncidentNotice $notice, ?string $error): void
    {
        rescue(fn () => NotificationSent::query()->create([
            'incident_id' => is_int($notice->incident->id) ? $notice->incident->id : null,
            'rule_id' => is_int($notice->rule->id) ? $notice->rule->id : null,
            'channel' => $name,
            'kind' => $notice->kind->value,
            'delivered' => $error === null,
            'error' => $error === null ? null : mb_substr($error, 0, 1000),
            'sent_at' => now(),
        ]), report: false);
    }
}
