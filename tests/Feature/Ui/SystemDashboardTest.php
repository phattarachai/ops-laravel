<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;
use Phattarachai\OpsLaravel\Models\Deploy;
use Phattarachai\OpsLaravel\Models\QueueSample;
use Phattarachai\OpsLaravel\Models\SlowQuery;
use Phattarachai\OpsLaravel\Ops;

beforeEach(function (): void {
    Ops::auth(fn (Request $request): bool => true);
    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:15:20'));
});

it('shows slow queries, queue depth, exceptions and deploys for the window', function (): void {
    SlowQuery::query()->create(['bucket' => '2026-10-08 10:00:00', 'hash' => 'h1', 'connection' => 'testing', 'sql' => 'select * from orders where id = ?', 'location' => 'app/Foo.php:12', 'count' => 4, 'sum_ms' => 4000, 'max_ms' => 1800]);
    QueueSample::query()->create(['bucket' => '2026-10-08 10:10:00', 'connection' => 'redis', 'queue' => 'default', 'size' => 12, 'oldest_wait_seconds' => 90]);
    Deploy::query()->create(['release' => 'abc123', 'deployed_at' => '2026-10-08 09:00:00']);
    report(new RuntimeException('boom'));
    rollupAt('2026-10-08 10:16:05');

    $this->get(route('ops.system'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'system')
            ->where('queries.0.count', 4)
            ->where('queries.0.avg_label', '1s')
            ->where('queues.0.name', 'redis:default')
            ->where('queues.0.size', 12)
            ->where('exceptions.0.class', RuntimeException::class)
            ->where('deploys.0.release', 'abc123')
            ->etc());
});
