<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Phattarachai\OpsLaravel\Ui\OpsCache;

use function Illuminate\Support\defer;

/** @return Collection<string, array{owner: ?string, expiresAt: ?Carbon}> */
function opsCacheFlexibleLocks(): Collection
{
    return collect(Cache::store()->getStore()->locks)
        ->filter(fn (array $lock, string $name): bool => str_starts_with($name, 'illuminate:cache:flexible:lock:'));
}

it('takes the stale-while-revalidate refresh lock with a bounded expiry', function (string $method, int $staleAfter): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00:00'));
    OpsCache::$method('board', fn (): string => 'first');

    $this->travel($staleAfter)->seconds();
    $locks = null;

    expect(OpsCache::$method('board', function () use (&$locks): string {
        $locks = opsCacheFlexibleLocks();

        return 'second';
    }))->toBe('first');

    defer()->invoke();

    expect($locks)->toHaveCount(1)
        ->and($locks->first()['expiresAt']?->timestamp)->toBe(now()->addSeconds(OpsCache::LOCK_SECONDS)->timestamp)
        ->and(OpsCache::$method('board', fn (): string => 'third'))->toBe('second');
})->with([
    'remember' => ['remember', OpsCache::FRESH_SECONDS + 1],
    'rememberClosedDays' => ['rememberClosedDays', 3601],
]);
