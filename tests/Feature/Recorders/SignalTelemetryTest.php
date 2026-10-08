<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Phattarachai\OpsCore\Alerts\ProbeLevel;
use Phattarachai\OpsLaravel\Models\ProbeState;
use Phattarachai\OpsLaravel\Models\SignalRollup;
use Phattarachai\OpsLaravel\Models\SignalValue;
use Phattarachai\OpsLaravel\Ops;

it('rolls host signals into counts and distinct values', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:15:20'));

    Ops::signal('cross_tenant', key: 'user:7', meta: ['tenant' => 'a']);
    Ops::signal('cross_tenant', key: 'user:7', meta: ['tenant' => 'b']);
    Ops::signal('cross_tenant', key: 'user:7', meta: ['tenant' => 'a']);

    rollupAt('2026-10-08 10:16:05');

    expect(SignalRollup::query()->sole())->key->toBe('user:7')->count->toBe(3)
        ->and(SignalValue::query()->orderBy('value')->pluck('value')->all())->toBe(['a', 'b']);
});

it('keeps the latest probe reading per key', function (): void {
    Ops::probe('network', 'vpn', ProbeLevel::Warning, 1.0, 'partial');
    Ops::probe('network', 'vpn', ProbeLevel::Critical, 0.0, 'down');

    expect(ProbeState::query()->sole())->level->toBe(ProbeLevel::Critical)->message->toBe('down');
});
