<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Console;

use Illuminate\Console\Command;
use Phattarachai\OpsCore\Alerts\AlertEngine;
use Phattarachai\OpsLaravel\Alerts\NoticeDispatcher;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Ops;

/**
 * Evaluates every rule once (scheduled right after ops:rollup) and sends what the incident
 * lifecycle decided. A rule that throws is reported and skipped.
 */
final class AlertsCommand extends Command
{
    protected $signature = 'ops:alerts';

    protected $description = 'Evaluate Ops alert rules and send incident notices';

    public function handle(AlertEngine $engine, NoticeDispatcher $dispatcher): int
    {
        if (! Ops::recording()) {
            return self::SUCCESS;
        }

        $now = now()->toDateTimeImmutable();
        $notices = [];

        foreach (Rule::query()->orderBy('id')->get() as $rule) {
            $notices = [...$notices, ...(array) rescue(fn (): array => $engine->runRule($rule->toSpec(), $now, app()->environment()), [])];
        }

        $sent = $dispatcher->dispatch($notices);

        $this->components->info(count($notices)." notices, {$sent} deliveries.");

        return self::SUCCESS;
    }
}
