<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Phattarachai\OpsLaravel\Models\CacheRollup;
use Phattarachai\OpsLaravel\Models\Deploy;
use Phattarachai\OpsLaravel\Models\QueueSample;
use Phattarachai\OpsLaravel\Models\SlowQuery;
use Phattarachai\OpsLaravel\Recorders\CacheRecorder;
use Phattarachai\OpsLaravel\Recorders\QueryRecorder;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-10-08 10:15:20')));

it('groups slow queries by their normalised sql', function (): void {
    app(OpsSettings::class)->set('recorders.queries.threshold_ms', 0);

    DB::select('select 1 as one where 1 = ?', [1]);
    DB::select('select 1 as one where 1 = ?', [2]);

    rollupAt('2026-10-08 10:16:05');

    $query = SlowQuery::query()->where('sql', 'like', 'select ? as one%')->sole();

    expect($query->count)->toBe(2)
        ->and($query->bucket->format('H:i'))->toBe('10:00');
});

it('masks literals and in-lists when normalising', function (): void {
    expect(QueryRecorder::normalize("select * from users where id in (?, ?, ?) and name = 'x' and age > 30"))
        ->toBe('select * from users where id in (?) and name = ? and age > ?');
});

it('samples cache hits and misses per key pattern', function (): void {
    app(OpsSettings::class)->set('recorders.cache.sample_rate', 1);

    Cache::put('user:1:profile', 'x');
    Cache::get('user:1:profile');
    Cache::get('user:2:profile');
    Cache::get('ops:internal');

    rollupAt('2026-10-08 10:16:05');

    expect(CacheRollup::query()->sole())->key->toBe('user:{n}:profile')->hits->toBe(1)->misses->toBe(1)
        ->and(CacheRecorder::pattern('job:3f2504e0-4f89-11d3-9a0c-0305e82c3301'))->toBe('job:{uuid}');
});

it('samples queue depth every rollup and records a deploy once per release', function (): void {
    config(['ops.release' => 'abc123']);

    rollupAt('2026-10-08 10:16:05');
    rollupAt('2026-10-08 10:17:05');

    expect(QueueSample::query()->count())->toBe(2)
        ->and(Deploy::query()->pluck('release')->all())->toBe(['abc123']);
});
