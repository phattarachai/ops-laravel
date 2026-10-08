<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Recorders;

use Illuminate\Auth\Events\Failed;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use Phattarachai\OpsLaravel\Signals\BuiltInSignals;

final readonly class FailedLoginRecorder
{
    public function __construct(private SignalRecorder $signals, private OpsSettings $settings) {}

    public function handle(Failed $event): void
    {
        if (! $this->settings->bool('recorders.security.enabled')) {
            return;
        }

        $ip = request()->ip();
        $identifier = $this->identifier($event->credentials);

        if ($ip !== null) {
            $this->signals->record(BuiltInSignals::AUTH_FAILED, "ip:{$ip}", ['account' => $identifier]);
        }

        if ($identifier !== '') {
            $this->signals->record(BuiltInSignals::AUTH_FAILED, "account:{$identifier}", ['ip' => (string) $ip]);
        }
    }

    /** @param  array<string, mixed>  $credentials */
    private function identifier(array $credentials): string
    {
        $value = $credentials['email'] ?? $credentials['username'] ?? $credentials['phone'] ?? null;

        return is_string($value) && $value !== '' ? substr(hash('sha256', mb_strtolower($value)), 0, 16) : '';
    }
}
