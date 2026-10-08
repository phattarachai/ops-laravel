<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Alerts;

use Phattarachai\OpsCore\Alerts\IncidentNotice;
use Phattarachai\OpsCore\Alerts\NoticeKind;
use Phattarachai\OpsCore\Alerts\RuleType;

/**
 * The words every channel sends for one notice, in the configured locale.
 */
final readonly class NoticeText
{
    public function __construct(private IncidentNotice $notice) {}

    public function kind(): string
    {
        return $this->trans("kind.{$this->notice->kind->value}");
    }

    public function subject(): string
    {
        return "{$this->kind()} · ".$this->trans('subject', [
            'severity' => $this->trans("severity.{$this->notice->incident->severity->value}"),
            'rule' => $this->notice->rule->name,
            'key' => $this->notice->incident->key === '' ? '—' : $this->notice->incident->key,
        ]);
    }

    /** @return list<string> */
    public function lines(): array
    {
        $incident = $this->notice->incident;
        $lines = [$this->value(), $this->trans('opened_at', ['time' => $incident->openedAt->format('Y-m-d H:i')])];

        if ($this->notice->kind === NoticeKind::Resolved && $incident->resolvedAt !== null) {
            $lines[] = $this->trans('resolved_at', ['time' => $incident->resolvedAt->format('Y-m-d H:i')]);
            $lines[] = $this->trans('peak', ['value' => $this->number($incident->peakValue)]);
        }

        return $lines;
    }

    public function url(): ?string
    {
        return $this->notice->incident->id === null ? null : url(trim((string) config('ops.path'), '/').'/incidents/'.$this->notice->incident->id);
    }

    public function openLabel(): string
    {
        return $this->trans('open_dashboard');
    }

    private function value(): string
    {
        $rule = $this->notice->rule;
        $value = $this->number($this->notice->incident->lastValue);

        return match ($rule->type) {
            RuleType::Threshold => $this->trans('value.threshold', ['value' => $value, 'window' => $this->duration($rule->windowSeconds)]),
            RuleType::Rate => $this->trans('value.rate', ['value' => $value, 'window' => $this->duration($rule->windowSeconds)]),
            RuleType::Absence => $this->trans('value.absence', ['window' => $this->duration((int) $this->notice->incident->lastValue)]),
            RuleType::State => $this->trans('value.state', ['level' => (string) ($this->notice->incident->context['message'] ?? $this->notice->incident->context['level'] ?? '')]),
        };
    }

    private function duration(int $seconds): string
    {
        return $seconds % 60 === 0
            ? trans_choice('ops::alerts.minutes', intdiv($seconds, 60), [], $this->locale())
            : trans_choice('ops::alerts.seconds', $seconds, [], $this->locale());
    }

    private function number(float $value): string
    {
        return $value === floor($value) ? number_format($value) : number_format($value, 1);
    }

    /** @param  array<string, string>  $replace */
    private function trans(string $key, array $replace = []): string
    {
        return (string) trans("ops::alerts.{$key}", $replace, $this->locale());
    }

    private function locale(): string
    {
        return (string) (config('ops.locale') ?? app()->getLocale());
    }
}
