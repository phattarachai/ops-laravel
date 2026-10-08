<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Recorders;

use Illuminate\Log\Events\MessageLogged;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use Phattarachai\OpsLaravel\Signals\BuiltInSignals;
use Throwable;

/**
 * Counts reported exceptions per class as the `exception` signal (with the throw location as a
 * distinct value), so the System page and an alert rule can see a spike without an error tracker.
 */
final readonly class ExceptionRecorder
{
    public function __construct(private SignalRecorder $signals, private OpsSettings $settings) {}

    public function handle(MessageLogged $event): void
    {
        $exception = $event->context['exception'] ?? null;

        if (! $exception instanceof Throwable || ! $this->settings->bool('recorders.exceptions.enabled')) {
            return;
        }

        $this->signals->record(BuiltInSignals::EXCEPTION, $exception::class, ['location' => $this->location($exception)]);
    }

    private function location(Throwable $exception): string
    {
        $file = str_starts_with($exception->getFile(), base_path()) ? ltrim(substr($exception->getFile(), strlen(base_path())), DIRECTORY_SEPARATOR) : basename($exception->getFile());

        return "{$file}:{$exception->getLine()}";
    }
}
