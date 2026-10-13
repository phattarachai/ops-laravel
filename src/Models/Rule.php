<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Models;

use Carbon\CarbonImmutable;
use Override;
use Phattarachai\OpsCore\Alerts\ProbeLevel;
use Phattarachai\OpsCore\Alerts\RuleSpec;
use Phattarachai\OpsCore\Alerts\RuleType;
use Phattarachai\OpsCore\Alerts\Severity;

/**
 * @property int $id
 * @property string|null $slug
 * @property string $name
 * @property RuleType $type
 * @property string $signal
 * @property float $threshold
 * @property int $window_seconds
 * @property Severity $severity
 * @property string|null $denominator_signal
 * @property int $min_samples
 * @property string|null $distinct_field
 * @property list<string>|null $keys
 * @property ProbeLevel $min_level
 * @property int $cooldown_seconds
 * @property int $resolve_after_seconds
 * @property list<string>|null $environments
 * @property list<string>|null $channels
 * @property bool $active
 * @property bool $notify_on_resolve
 * @property bool $remind_warnings
 * @property CarbonImmutable|null $muted_until
 */
final class Rule extends OpsModel
{
    protected $table = 'ops_rules';

    public function toSpec(): RuleSpec
    {
        return new RuleSpec(
            id: $this->id,
            name: $this->name,
            type: $this->type,
            signal: $this->signal,
            threshold: $this->threshold,
            windowSeconds: $this->window_seconds,
            severity: $this->severity,
            denominatorSignal: $this->denominator_signal,
            minSamples: $this->min_samples,
            distinctField: $this->distinct_field,
            keys: array_values($this->keys ?? []),
            minLevel: $this->min_level,
            cooldownSeconds: $this->cooldown_seconds,
            resolveAfterSeconds: $this->resolve_after_seconds,
            environments: array_values($this->environments ?? []),
            channels: array_values($this->channels ?? []),
            active: $this->active,
            mutedUntil: $this->muted_until?->toDateTimeImmutable(),
            notifyOnResolve: $this->notify_on_resolve,
            remindWarnings: $this->remind_warnings ?? true,
        );
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'type' => RuleType::class,
            'severity' => Severity::class,
            'min_level' => ProbeLevel::class,
            'threshold' => 'float',
            'keys' => 'array',
            'environments' => 'array',
            'channels' => 'array',
            'active' => 'boolean',
            'notify_on_resolve' => 'boolean',
            'remind_warnings' => 'boolean',
            'muted_until' => 'immutable_datetime',
        ];
    }
}
