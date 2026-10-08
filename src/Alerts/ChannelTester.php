<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Alerts;

use DateTimeImmutable;
use Phattarachai\OpsCore\Alerts\Breach;
use Phattarachai\OpsCore\Alerts\Incident;
use Phattarachai\OpsCore\Alerts\IncidentNotice;
use Phattarachai\OpsCore\Alerts\NoticeKind;
use Phattarachai\OpsCore\Alerts\RuleSpec;
use Phattarachai\OpsCore\Alerts\RuleType;
use Phattarachai\OpsCore\Alerts\Severity;

final readonly class ChannelTester
{
    public function __construct(private NoticeDispatcher $dispatcher) {}

    public function test(string $channel): bool
    {
        return $this->dispatcher->send($channel, $this->notice($channel));
    }

    private function notice(string $channel): IncidentNotice
    {
        $now = new DateTimeImmutable;
        $rule = new RuleSpec('test', (string) trans('ops::alerts.test_subject', [], (string) (config('ops.locale') ?? app()->getLocale())), RuleType::State, 'ops.test');
        $message = (string) trans('ops::alerts.test_body', ['channel' => $channel], (string) (config('ops.locale') ?? app()->getLocale()));

        return new IncidentNotice(NoticeKind::Test, Incident::open('test', new Breach($channel, 1, Severity::Warning, ['message' => $message]), $now), $rule);
    }
}
