<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Phattarachai\OpsLaravel\Models\OutgoingRollup;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-10-08 10:15:20')));

it('records every Http client call by endpoint, status and outcome', function (): void {
    Http::fake([
        'api.vendor.test/*' => Http::sequence()->push(['ok' => true])->push('down', 503),
    ]);

    Http::get('https://api.vendor.test/orders');
    Http::get('https://api.vendor.test/orders');

    rollupAt('2026-10-08 10:16:05');

    expect(OutgoingRollup::query()->where('grain', 'minute')->orderBy('status')->get(['endpoint', 'status', 'outcome', 'calls'])->toArray())
        ->toBe([
            ['endpoint' => 'api.vendor.test', 'status' => 200, 'outcome' => 'ok', 'calls' => 1],
            ['endpoint' => 'api.vendor.test', 'status' => 503, 'outcome' => 'http_5xx', 'calls' => 1],
        ]);
});

it('counts a failed connection without a latency', function (): void {
    Http::fake(['*' => Http::failedConnection('cURL error 28: Operation timed out after 10000 milliseconds')]);

    expect(fn () => Http::get('https://slow.vendor.test/ping'))->toThrow(ConnectionException::class);

    rollupAt('2026-10-08 10:16:05');

    expect(OutgoingRollup::query()->where('grain', 'minute')->sole())
        ->status->toBe(0)
        ->outcome->toBe('timeout')
        ->calls->toBe(1)
        ->timed->toBe(0);
});

it('names endpoints through the host resolver and skips ignored hosts', function (): void {
    Http::fake();
    Ops::outgoingEndpointUsing(fn (Request $request): ?string => str_contains($request->url(), '/orders') ? 'vendor.orders' : null);
    app(OpsSettings::class)->set('recorders.outgoing.ignore_hosts', ['hooks.slack.test']);

    Http::get('https://api.vendor.test/orders/1');
    Http::post('https://hooks.slack.test/services/x');

    rollupAt('2026-10-08 10:16:05');

    expect(OutgoingRollup::query()->where('grain', 'minute')->pluck('endpoint')->all())->toBe(['vendor.orders']);
});
