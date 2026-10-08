<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Incidents;

use Carbon\CarbonImmutable;
use Phattarachai\OpsCore\Alerts\IncidentStatus;
use Phattarachai\OpsCore\Alerts\ProbeLevel;
use Phattarachai\OpsCore\Alerts\RuleType;
use Phattarachai\OpsCore\Alerts\Severity;
use Phattarachai\OpsLaravel\Alerts\ChannelRegistry;
use Phattarachai\OpsLaravel\Alerts\DefaultRules;
use Phattarachai\OpsLaravel\Models\Incident;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Signals\BuiltInSignals;
use Phattarachai\OpsLaravel\Ui\Format;

/**
 * The Rules page: every rule with its editable fields, and the options the rule form offers.
 */
final class RuleRows
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        $open = Incident::query()
            ->where('status', IncidentStatus::Open->value)
            ->selectRaw('rule_id, count(*) as open_count')
            ->groupBy('rule_id')
            ->pluck('open_count', 'rule_id');

        return Rule::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Rule $rule): array => self::row($rule, (int) ($open[$rule->id] ?? 0)))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public static function options(): array
    {
        $slugs = Rule::query()->whereNotNull('slug')->pluck('slug')->all();
        $definitions = DefaultRules::definitions();

        return [
            'types' => array_map(fn (RuleType $type): string => $type->value, RuleType::cases()),
            'severities' => array_map(fn (Severity $severity): string => $severity->value, Severity::cases()),
            'levels' => [ProbeLevel::Warning->value, ProbeLevel::Critical->value],
            'signals' => array_map(fn (string $signal, string $description): array => ['value' => $signal, 'description' => $description], array_keys(BuiltInSignals::all()), BuiltInSignals::all()),
            'channels' => app(ChannelRegistry::class)->names(),
            'environment' => app()->environment(),
            'missing_defaults' => count(array_filter($definitions, fn (array $definition): bool => ! in_array($definition['slug'], $slugs, true))),
            'store_url' => route('ops.rules.store'),
            'defaults_url' => route('ops.rules.defaults'),
            'incidents_url' => route('ops.incidents'),
        ];
    }

    /** @return array<string, mixed> */
    private static function row(Rule $rule, int $openIncidents): array
    {
        return [
            'id' => $rule->id,
            'slug' => $rule->slug,
            'name' => $rule->name,
            'type' => $rule->type->value,
            'signal' => $rule->signal,
            'threshold' => $rule->threshold,
            'window_seconds' => $rule->window_seconds,
            'distinct_field' => $rule->distinct_field,
            'denominator_signal' => $rule->denominator_signal,
            'min_samples' => $rule->min_samples,
            'min_level' => $rule->min_level->value,
            'severity' => $rule->severity->value,
            'keys' => array_values($rule->keys ?? []),
            'environments' => array_values($rule->environments ?? []),
            'channels' => array_values($rule->channels ?? []),
            'cooldown_seconds' => $rule->cooldown_seconds,
            'resolve_after_seconds' => $rule->resolve_after_seconds,
            'active' => $rule->active,
            'notify_on_resolve' => $rule->notify_on_resolve,
            'is_muted' => $rule->muted_until !== null && $rule->muted_until->isAfter(CarbonImmutable::now()),
            'muted_until' => Format::date($rule->muted_until),
            'muted_until_iso' => $rule->muted_until?->toIso8601String(),
            'open_incidents' => $openIncidents,
            'update_url' => route('ops.rules.update', ['rule' => $rule->id]),
            'destroy_url' => route('ops.rules.destroy', ['rule' => $rule->id]),
        ];
    }
}
