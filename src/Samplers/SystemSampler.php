<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Samplers;

use Carbon\CarbonImmutable;
use Phattarachai\OpsCore\Alerts\ProbeLevel;
use Phattarachai\OpsLaravel\Models\SystemSample;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

/**
 * Load, memory and disk of the box running the scheduler, once a minute, plus `system.*`
 * probes (warning / critical from the thresholds settings) the state rules can watch.
 */
final readonly class SystemSampler
{
    public function __construct(private OpsSettings $settings) {}

    public function sample(CarbonImmutable $minute): bool
    {
        if (! $this->settings->bool('recorders.system.enabled')) {
            return false;
        }

        $host = mb_substr((string) gethostname(), 0, 100);
        $reading = ['load_1' => $this->load(), 'cpus' => $this->cpus(), 'memory_used_pct' => $this->memoryUsedPct(), 'disk_used_pct' => $this->diskUsedPct()];

        SystemSample::query()->upsert([['bucket' => $minute, 'host' => $host, ...$reading]], ['bucket', 'host'], array_keys($reading));

        $this->probe('system.memory', $host, $reading['memory_used_pct']);
        $this->probe('system.disk', $host, $reading['disk_used_pct']);
        $this->probe('system.load', $host, $reading['load_1'] !== null && $reading['cpus'] !== null ? $reading['load_1'] / max(1, $reading['cpus']) * 100 : null);

        return true;
    }

    private function probe(string $signal, string $host, ?float $percent): void
    {
        if ($percent === null) {
            return;
        }

        $warning = (float) $this->settings->get("thresholds.{$signal}.warning", 85);
        $critical = (float) $this->settings->get("thresholds.{$signal}.critical", 95);

        $level = match (true) {
            $percent >= $critical => ProbeLevel::Critical,
            $percent >= $warning => ProbeLevel::Warning,
            default => ProbeLevel::Ok,
        };

        Ops::probe($signal, $host, $level, round($percent, 1), round($percent, 1).'%');
    }

    private function load(): ?float
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

        return is_array($load) ? round((float) $load[0], 2) : null;
    }

    private function cpus(): ?int
    {
        $info = @file_get_contents('/proc/cpuinfo');

        return is_string($info) ? max(1, substr_count($info, 'processor')) : null;
    }

    private function memoryUsedPct(): ?float
    {
        $info = @file_get_contents('/proc/meminfo');

        if (! is_string($info) || ! preg_match('/MemTotal:\s+(\d+)/', $info, $total) || ! preg_match('/MemAvailable:\s+(\d+)/', $info, $available)) {
            return null;
        }

        return round((1 - (int) $available[1] / max(1, (int) $total[1])) * 100, 1);
    }

    private function diskUsedPct(): ?float
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());

        return is_float($total) && is_float($free) && $total > 0 ? round((1 - $free / $total) * 100, 1) : null;
    }
}
