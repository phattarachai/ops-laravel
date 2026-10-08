<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;
use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsLaravel\Models\OutgoingRollup;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Ui\Format;

function outgoingRollup(string $endpoint, string $bucket, string $outcome, int $calls, int $status = 200, int $ms = 200, string $grain = 'hour'): void
{
    $histogram = LatencyHistogram::empty();
    $histogram[LatencyHistogram::binMs($ms)] = $calls;

    OutgoingRollup::query()->insert([
        'grain' => $grain,
        'bucket' => CarbonImmutable::parse($bucket),
        'endpoint' => $endpoint,
        'method' => 'POST',
        'status' => $status,
        'outcome' => $outcome,
        'calls' => $calls,
        'timed' => $calls,
        'sum_ms' => $calls * $ms,
        'max_ms' => $ms,
        'p50_ms' => $ms,
        'p95_ms' => $ms,
        'histogram' => json_encode($histogram),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function (): void {
    Ops::auth(fn (Request $request): bool => true);
    $this->travelTo(CarbonImmutable::parse('2026-10-08 12:30:00'));

    outgoingRollup('api.stripe.test', '2026-10-08 10:00:00', 'ok', 90);
    outgoingRollup('api.stripe.test', '2026-10-08 10:00:00', 'http_5xx', 10, status: 503, ms: 1500);
    outgoingRollup('api.stripe.test', '2026-10-04 10:00:00', 'ok', 900);
    outgoingRollup('api.quiet.test', '2026-10-05 09:00:00', 'ok', 5);
    outgoingRollup('api.stripe.test', '2026-10-08 12:29:00', 'ok', 3, grain: 'minute');
});

it('lists every endpoint with its health against its own 7-day baseline', function (): void {
    $this->get(route('ops.outgoing'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'outgoing')
            ->has('endpoints', 2)
            ->where('endpoints.0.endpoint', 'api.quiet.test')
            ->where('endpoints.0.health', 'unknown')
            ->where('endpoints.0.is_dark', true)
            ->where('endpoints.1.endpoint', 'api.stripe.test')
            ->where('endpoints.1.calls', 100)
            ->where('endpoints.1.failed', 10)
            ->where('endpoints.1.error_pct', 10)
            ->where('endpoints.1.baseline_error_pct', 1)
            ->where('endpoints.1.deviation', 9)
            ->where('endpoints.1.health', 'degraded')
            ->where('endpoints.1.outcomes.http_5xx', 10)
            ->has('endpoints.1.spark', 20)
            ->has('endpoints.1.error_days', 7)
            ->where('endpoints.1.url', route('ops.outgoing.show', ['token' => Format::base64url('api.stripe.test')]))
            ->where('summary.endpoints', 2)
            ->where('summary.unhealthy', 1)
            ->where('summary.calls', 100)
            ->where('time_filter.window', '24h'));
});

it('carries the time window into the drill-in links', function (): void {
    $this->get(route('ops.outgoing', ['window' => '7d']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('endpoints.1.calls', 1000)
            ->where('endpoints.1.url', route('ops.outgoing.show', ['token' => Format::base64url('api.stripe.test'), 'window' => '7d'])));
});

it('shows one endpoint with its bars, outcomes, histogram and method/status split', function (): void {
    $this->get(route('ops.outgoing.show', ['token' => Format::base64url('api.stripe.test')]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'outgoing.show')
            ->where('endpoint.endpoint', 'api.stripe.test')
            ->where('endpoint.calls', 100)
            ->has('buckets', 24)
            ->where('buckets.21.ok', 90)
            ->where('buckets.21.http_5xx', 10)
            ->where('buckets.21.failed', 10)
            ->has('outcomes', 2)
            ->where('outcomes.0', ['kind' => 'ok', 'count' => 90, 'tone' => 'ok'])
            ->where('outcomes.1', ['kind' => 'http_5xx', 'count' => 10, 'tone' => 'fail'])
            ->has('histogram', LatencyHistogram::BIN_COUNT)
            ->has('statuses', 2)
            ->where('statuses.0.status', 200)
            ->where('statuses.0.calls', 90)
            ->where('statuses.1.status', 503)
            ->where('statuses.1.share', '10%')
            ->has('hour_of_day', 24)
            ->where('hour_of_day.10', ['runs' => 1000, 'failed' => 10])
            ->where('back_url', route('ops.outgoing')));
});

it('returns 404 for an unknown or malformed endpoint token', function (string $token): void {
    $this->get(route('ops.outgoing.show', ['token' => $token]))->assertNotFound();
})->with([
    'unknown endpoint' => Format::base64url('api.unknown.test'),
    'not base64' => '!!!',
]);
