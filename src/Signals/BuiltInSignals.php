<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Signals;

/**
 * Signals the package derives itself. Rules can use them like any `Ops::signal()` name.
 */
final class BuiltInSignals
{
    public const string USER_ROUTE = 'http.user_route';

    public const string JOB_RUN = 'job.run';

    public const string JOB_FAILED = 'job.failed';

    public const string SCHEDULE_RUN = 'schedule.run';

    public const string SCHEDULE_FAILED = 'schedule.failed';

    public const string OUTGOING_CALL = 'outgoing.call';

    public const string OUTGOING_FAILED = 'outgoing.failed';

    public const string AUTH_FAILED = 'auth.failed';

    /** @return array<string, string> signal => description */
    public static function all(): array
    {
        return [
            self::USER_ROUTE => 'Requests by one user on one route per minute (key user:{id}|{route})',
            self::JOB_RUN => 'Job runs per class',
            self::JOB_FAILED => 'Failed or timed-out job runs per class',
            self::SCHEDULE_RUN => 'Scheduled task runs per task (last seen = heartbeat)',
            self::SCHEDULE_FAILED => 'Failed scheduled task runs per task',
            self::OUTGOING_CALL => 'Outgoing calls per endpoint',
            self::OUTGOING_FAILED => 'Failed outgoing calls per endpoint',
            self::AUTH_FAILED => 'Failed logins per IP (ip:…) and per hashed account (account:…)',
            'http.{status}' => '401 / 403 / 404 / 419 / 429 responses per IP (ip:…) and user (user:…)',
        ];
    }
}
