<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;
use Phattarachai\OpsCore\Alerts\Incident as CoreIncident;
use Phattarachai\OpsCore\Alerts\IncidentStatus;
use Phattarachai\OpsCore\Alerts\Severity;

/**
 * @property int $id
 * @property int $rule_id
 * @property string $key
 * @property IncidentStatus $status
 * @property Severity $severity
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable $last_breached_at
 * @property CarbonImmutable|null $last_notified_at
 * @property CarbonImmutable|null $resolved_at
 * @property float $peak_value
 * @property float $last_value
 * @property int $notifications
 * @property array<string, mixed>|null $context
 * @property-read Rule|null $rule
 */
final class Incident extends OpsModel
{
    protected $table = 'ops_incidents';

    /** @return array<string, mixed> */
    public static function attributesFrom(CoreIncident $incident): array
    {
        return [
            'rule_id' => $incident->ruleId,
            'key' => $incident->key,
            'status' => $incident->status,
            'severity' => $incident->severity,
            'opened_at' => $incident->openedAt,
            'last_breached_at' => $incident->lastBreachedAt,
            'last_notified_at' => $incident->lastNotifiedAt,
            'resolved_at' => $incident->resolvedAt,
            'peak_value' => $incident->peakValue,
            'last_value' => $incident->lastValue,
            'notifications' => $incident->notifications,
            'context' => $incident->context,
        ];
    }

    /** @return BelongsTo<Rule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }

    public function toCore(): CoreIncident
    {
        return new CoreIncident(
            ruleId: $this->rule_id,
            key: $this->key,
            severity: $this->severity,
            openedAt: $this->opened_at->toDateTimeImmutable(),
            lastBreachedAt: $this->last_breached_at->toDateTimeImmutable(),
            peakValue: $this->peak_value,
            lastValue: $this->last_value,
            status: $this->status,
            lastNotifiedAt: $this->last_notified_at?->toDateTimeImmutable(),
            resolvedAt: $this->resolved_at?->toDateTimeImmutable(),
            notifications: $this->notifications,
            context: $this->context ?? [],
            id: $this->id,
        );
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'severity' => Severity::class,
            'opened_at' => 'immutable_datetime',
            'last_breached_at' => 'immutable_datetime',
            'last_notified_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'peak_value' => 'float',
            'last_value' => 'float',
            'context' => 'array',
        ];
    }
}
