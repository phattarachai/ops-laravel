<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Recorders;

use Illuminate\Database\Events\QueryExecuted;
use Phattarachai\OpsLaravel\Buffer\MinuteBuffer;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

/**
 * Queries at or over the threshold, grouped by their normalised SQL (literals masked). Ops'
 * own tables are left out so the dashboard does not chart itself.
 */
class QueryRecorder
{
    public const string STREAM = 'qry';

    public function __construct(
        protected MinuteBuffer $buffer,
        protected OpsSettings $settings,
    ) {}

    public static function normalize(string $sql): string
    {
        $masked = (string) preg_replace(["/'(?:[^'\\\\]|\\\\.)*'/", '/\b\d+(\.\d+)?\b/', '/\s+/', '/\((\?(, )?)+\)/'], ['?', '?', ' ', '(?)'], $sql);

        return mb_substr(trim($masked), 0, 2000);
    }

    public function record(QueryExecuted $event): void
    {
        if (str_contains($event->sql, 'ops_') || ! Ops::recording()) {
            return;
        }

        rescue(function () use ($event): void {
            if (! $this->settings->bool('recorders.queries.enabled') || $event->time < $this->settings->int('recorders.queries.threshold_ms', 500)) {
                return;
            }

            $sql = self::normalize($event->sql);
            $hash = sha1("{$event->connectionName}|{$sql}");
            $milliseconds = (int) round($event->time);

            $this->buffer->add(self::STREAM, now(), ["{$hash}#c" => 1, "{$hash}#ms" => $milliseconds], [$hash => $milliseconds], [
                (string) json_encode([$hash, $event->connectionName, $sql, $this->location()], JSON_UNESCAPED_UNICODE),
            ]);
        }, report: false);
    }

    protected function location(): ?string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60) as $frame) {
            $file = $frame['file'] ?? '';

            if ($file !== '' && ! str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR) && str_starts_with($file, base_path())) {
                return mb_substr(ltrim(substr($file, strlen(base_path())), DIRECTORY_SEPARATOR).':'.($frame['line'] ?? 0), 0, 255);
            }
        }

        return null;
    }
}
