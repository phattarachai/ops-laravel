<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Alerts;

use Phattarachai\OpsCore\Alerts\RuleType;
use Phattarachai\OpsCore\Alerts\Severity;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Signals\BuiltInSignals;

/**
 * Starting rules seeded by ops:install, production only. Seeding never overwrites a rule the
 * host already has (matched by slug), so edits on the Rules page survive a re-run.
 */
final class DefaultRules
{
    /** @return list<array{slug: string, name: string, type: RuleType, signal: string, threshold: int, window_seconds: int, distinct_field?: string, severity?: Severity, remind_warnings?: bool}> */
    public static function definitions(): array
    {
        return [
            ['slug' => 'http-403-burst', 'name' => '403 burst', 'type' => RuleType::Threshold, 'signal' => 'http.403', 'threshold' => 20, 'window_seconds' => 300],
            ['slug' => 'http-429-burst', 'name' => '429 burst', 'type' => RuleType::Threshold, 'signal' => 'http.429', 'threshold' => 10, 'window_seconds' => 300],
            ['slug' => 'cross-tenant', 'name' => 'Cross-tenant probing', 'type' => RuleType::Threshold, 'signal' => 'cross_tenant', 'distinct_field' => 'tenant', 'threshold' => 3, 'window_seconds' => 600, 'severity' => Severity::Critical],
            ['slug' => 'failed-logins', 'name' => 'Failed logins', 'type' => RuleType::Threshold, 'signal' => BuiltInSignals::AUTH_FAILED, 'threshold' => 20, 'window_seconds' => 600],
            ['slug' => 'user-route-flood', 'name' => 'One user flooding a route', 'type' => RuleType::Threshold, 'signal' => BuiltInSignals::USER_ROUTE, 'threshold' => 120, 'window_seconds' => 60],
            ['slug' => 'schedule-health', 'name' => 'Scheduled task failing or missed', 'type' => RuleType::State, 'signal' => BuiltInSignals::SCHEDULE_HEALTH, 'threshold' => 0, 'window_seconds' => 0, 'remind_warnings' => false],
            ['slug' => 'job-failures', 'name' => 'Repeated job failures', 'type' => RuleType::Threshold, 'signal' => BuiltInSignals::JOB_FAILED, 'threshold' => 3, 'window_seconds' => 900],
        ];
    }

    public static function seed(): int
    {
        $created = 0;

        foreach (self::definitions() as $definition) {
            $rule = Rule::query()->firstOrCreate(['slug' => $definition['slug']], [
                'cooldown_seconds' => 900,
                'resolve_after_seconds' => 300,
                'environments' => ['production'],
                'channels' => [],
                'severity' => Severity::Warning,
                ...$definition,
            ]);

            $created += $rule->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }
}
