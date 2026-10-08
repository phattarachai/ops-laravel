<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Incidents;

use Carbon\CarbonImmutable;
use Phattarachai\OpsLaravel\Models\Incident;
use Phattarachai\OpsLaravel\Models\NotificationSent;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;

/**
 * One incident: its row, the rule behind it, its context and a timeline of breaches and the
 * notifications sent for it (ops_notifications_sent).
 */
final readonly class IncidentDetail
{
    public const int NOTIFICATION_LIMIT = 200;

    private const array ORDER = ['opened' => 0, 'breach' => 1, 'notification' => 2, 'resolved' => 3];

    public function __construct(private Incident $incident) {}

    /**
     * @return array{incident: array<string, mixed>, rule: array<string, mixed>|null, timeline: list<array<string, mixed>>, context: array<string, mixed>}
     */
    public function toProps(): array
    {
        return OpsCache::remember("incidents:detail:{$this->incident->id}", fn (): array => [
            'incident' => IncidentPresenter::row($this->incident),
            'rule' => $this->rule($this->incident->rule),
            'timeline' => $this->timeline(),
            'context' => $this->incident->context ?? [],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function rule(?Rule $rule): ?array
    {
        return $rule === null ? null : [
            'id' => $rule->id,
            'name' => $rule->name,
            'type' => $rule->type->value,
            'signal' => $rule->signal,
            'threshold' => $rule->threshold,
            'window_seconds' => $rule->window_seconds,
            'denominator_signal' => $rule->denominator_signal,
            'min_level' => $rule->min_level->value,
            'severity' => $rule->severity->value,
            'url' => route('ops.rules', ['rule' => $rule->id]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function timeline(): array
    {
        $events = [
            $this->event('opened', $this->incident->opened_at, ['value' => IncidentPresenter::value($this->incident, $this->incident->peak_value)]),
            ...$this->notifications(),
        ];

        if (! $this->incident->last_breached_at->equalTo($this->incident->opened_at)) {
            $events[] = $this->event('breach', $this->incident->last_breached_at, ['value' => IncidentPresenter::value($this->incident, $this->incident->last_value)]);
        }

        if ($this->incident->resolved_at !== null) {
            $events[] = $this->event('resolved', $this->incident->resolved_at);
        }

        usort($events, fn (array $a, array $b): int => [$a['at_iso'], $a['order']] <=> [$b['at_iso'], $b['order']]);

        return $events;
    }

    /** @return list<array<string, mixed>> */
    private function notifications(): array
    {
        return NotificationSent::query()
            ->where('incident_id', $this->incident->id)
            ->orderBy('sent_at')
            ->orderBy('id')
            ->limit(self::NOTIFICATION_LIMIT)
            ->get()
            ->map(fn (NotificationSent $sent): array => $this->event('notification', $sent->sent_at, [
                'kind' => $sent->kind,
                'channel' => $sent->channel,
                'delivered' => $sent->delivered,
                'error' => DeliveryError::redact($sent->error),
            ]))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function event(string $type, CarbonImmutable $at, array $extra = []): array
    {
        return [
            'type' => $type,
            'at' => Format::date($at, 'j M H:i:s'),
            'at_iso' => $at->utc()->format('Y-m-d\TH:i:s\Z'),
            'order' => self::ORDER[$type] ?? 9,
            ...$extra,
        ];
    }
}
