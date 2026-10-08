<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Jobs;

use Illuminate\Support\Str;
use Phattarachai\OpsLaravel\Models\JobRun;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;

/**
 * How a job class is labelled everywhere: short name, namespace, URL token, the group from
 * `ops.ui.job_groups` (ordered prefix => label, first match wins; otherwise the namespace segment
 * after `App\Jobs\`, root jobs and notifications as "System", anything else "Other") and whether
 * Horizon silences it.
 */
final class JobIdentity
{
    private const string HORIZON_SILENCED = 'Laravel\\Horizon\\Contracts\\Silenced';

    private const string JOBS_ROOT = 'App\\Jobs\\';

    /** @var array<string, bool> */
    private static array $silenced = [];

    /**
     * @return array{job_class: string, job: string, namespace: ?string, token: string, group: string, silenced: bool}
     */
    public static function of(string $jobClass): array
    {
        return [
            'job_class' => $jobClass,
            'job' => class_basename($jobClass),
            'namespace' => str_contains($jobClass, '\\') ? Str::beforeLast($jobClass, '\\') : null,
            'token' => Format::base64url($jobClass),
            'group' => self::group($jobClass),
            'silenced' => self::isSilenced($jobClass),
        ];
    }

    public static function group(string $jobClass): string
    {
        foreach ((array) config('ops.ui.job_groups', []) as $prefix => $label) {
            if (str_starts_with($jobClass, (string) $prefix)) {
                return (string) $label;
            }
        }

        return self::fallbackGroup($jobClass);
    }

    /** @return list<string> */
    public static function collapsedGroups(): array
    {
        return array_values(array_map(strval(...), (array) config('ops.ui.collapsed_job_groups', [])));
    }

    public static function isSilenced(string $jobClass): bool
    {
        return in_array($jobClass, (array) config('horizon.silenced', []), true) || self::implementsSilenced($jobClass);
    }

    /** The recorded job class behind a token, or null when the token is malformed or unknown. */
    public static function decode(string $token): ?string
    {
        $jobClass = Format::fromBase64url($token);

        return $jobClass !== null && in_array($jobClass, self::known(), true) ? $jobClass : null;
    }

    /** @return list<string> */
    public static function known(): array
    {
        return OpsCache::remember('jobs:known', fn (): array => JobRun::query()
            ->distinct()
            ->orderBy('job_class')
            ->pluck('job_class')
            ->all());
    }

    private static function implementsSilenced(string $jobClass): bool
    {
        return self::$silenced[$jobClass] ??= interface_exists(self::HORIZON_SILENCED)
            && class_exists($jobClass)
            && is_subclass_of($jobClass, self::HORIZON_SILENCED);
    }

    private static function fallbackGroup(string $jobClass): string
    {
        $rest = Str::after($jobClass, self::JOBS_ROOT);

        return match (true) {
            str_starts_with($jobClass, 'App\\Notifications\\') => 'System',
            ! str_starts_with($jobClass, self::JOBS_ROOT) => 'Other',
            str_contains($rest, '\\') => Str::before($rest, '\\'),
            default => 'System',
        };
    }
}
