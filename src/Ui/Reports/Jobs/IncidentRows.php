<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Phattarachai\OpsCore\Failures\Failure;
use Phattarachai\OpsCore\Failures\FailureGroup;
use Phattarachai\OpsCore\Failures\FailureGrouper;
use Phattarachai\OpsLaravel\Ui\Format;

/** Failed runs folded into incident rows (ops-core FailureGrouper) in the shape the kit's `<Incidents>` reads. */
final class IncidentRows
{
    private const int DOMAINS_SHOWN = 4;

    /**
     * @param  iterable<Failure>  $failures
     * @return list<array{at: string, at_iso: string, count: int, exception: string, message: string, duration: string, domains: list<string>, more_domains: int}>
     */
    public static function from(iterable $failures): array
    {
        return array_map(self::present(...), new FailureGrouper()->group($failures));
    }

    /**
     * @return array{at: string, at_iso: string, count: int, exception: string, message: string, duration: string, domains: list<string>, more_domains: int}
     */
    private static function present(FailureGroup $group): array
    {
        $started = CarbonImmutable::instance($group->startedAt);
        $ended = CarbonImmutable::instance($group->endedAt);
        $first = $group->first();
        $domains = $group->domains();
        $sameMinute = $started->format('Y-m-d H:i') === $ended->format('Y-m-d H:i');

        return [
            'at' => Format::date($started).($sameMinute ? '' : '–'.$ended->format('H:i')),
            'at_iso' => $started->toIso8601String(),
            'count' => $group->count(),
            'exception' => Failure::basename((string) $first->exception),
            'message' => Str::limit((string) $first->message, 160),
            'duration' => Format::duration($group->longestSeconds()),
            'domains' => array_slice($domains, 0, self::DOMAINS_SHOWN),
            'more_domains' => max(0, count($domains) - self::DOMAINS_SHOWN),
        ];
    }
}
