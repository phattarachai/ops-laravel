<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Support;

use Illuminate\Support\Facades\Context;

/**
 * Run origin, schedule run and parent job ride on hidden Context keys, so they reach every job a
 * request or scheduled task dispatches and never leak from one job to the next.
 */
final class OpsContext
{
    public const string SCHEDULE = 'schedule';

    public const string CONSOLE = 'console';

    private const string ORIGIN = 'ops.origin';

    private const string SCHEDULE_RUN = 'ops.schedule_run_id';

    private const string JOB_UUID = 'ops.job_uuid';

    public static function stampOrigin(string $origin, ?int $scheduleRunId = null): void
    {
        Context::addHidden(self::ORIGIN, $origin);

        if ($scheduleRunId === null) {
            Context::forgetHidden(self::SCHEDULE_RUN);

            return;
        }

        Context::addHidden(self::SCHEDULE_RUN, $scheduleRunId);
    }

    public static function origin(): ?string
    {
        $origin = Context::getHidden(self::ORIGIN);

        return is_string($origin) && $origin !== '' ? $origin : null;
    }

    public static function scheduleRunId(): ?int
    {
        $id = Context::getHidden(self::SCHEDULE_RUN);

        return is_numeric($id) ? (int) $id : null;
    }

    public static function jobUuid(): ?string
    {
        $uuid = Context::getHidden(self::JOB_UUID);

        return is_string($uuid) ? $uuid : null;
    }

    public static function enterJob(?string $uuid): void
    {
        if ($uuid === null) {
            Context::forgetHidden(self::JOB_UUID);

            return;
        }

        Context::addHidden(self::JOB_UUID, $uuid);
    }
}
