<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;
use Phattarachai\OpsLaravel\Buffer\MinuteBuffer;
use Phattarachai\OpsLaravel\Buffer\MinuteData;
use Phattarachai\OpsLaravel\Models\RequestRollup;
use Phattarachai\OpsLaravel\Models\SignalRollup;
use Phattarachai\OpsLaravel\Models\UserRequestRollup;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Recorders\RequestRecorder;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use Phattarachai\OpsLaravel\Signals\BuiltInSignals;

beforeEach(function (): void {
    Route::get('api/things/{id}', fn () => response()->json(['ok' => true]))->name('things.show');
    Route::get('api/limited', fn () => response('slow down', 429));
    Route::get('ops/home', fn () => 'dashboard');

    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:15:20'));
});

function actingUser(int $id): User
{
    $user = new User;
    $user->forceFill(['id' => $id]);

    return $user;
}

it('rolls a minute of requests into route rows with percentiles', function (): void {
    $this->getJson('api/things/1')->assertOk();
    $this->getJson('api/things/2')->assertOk();
    $this->getJson('api/limited')->assertStatus(429);

    rollupAt('2026-10-08 10:16:05');

    $rows = RequestRollup::query()->where('grain', 'minute')->get()->keyBy('route');

    expect($rows)->toHaveCount(2)
        ->and($rows['things.show'])
        ->requests->toBe(2)
        ->status->toBe(200)
        ->method->toBe('GET')
        ->segment->toBe('guest')
        ->bucket->format('H:i')->toBe('10:15')
        ->and(array_sum($rows['things.show']->histogram))->toBe(2)
        ->and($rows['things.show']->p95_ms)->not->toBeNull()
        ->and($rows['api/limited']->status)->toBe(429);
});

it('folds the minute rows into their hour', function (): void {
    $this->getJson('api/things/1');
    rollupAt('2026-10-08 10:16:05');

    $this->getJson('api/things/1');
    rollupAt('2026-10-08 10:17:05');

    $hour = RequestRollup::query()->where('grain', 'hour')->where('route', 'things.show')->sole();

    expect($hour->requests)->toBe(2)
        ->and($hour->bucket->format('H:i'))->toBe('10:00')
        ->and(array_sum($hour->histogram))->toBe(2);
});

it('rolls each minute exactly once', function (): void {
    $this->getJson('api/things/1');
    rollupAt('2026-10-08 10:16:05');

    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:15:50'));
    $this->getJson('api/things/1');
    cache()->forget('ops:rollup:high-water');
    rollupAt('2026-10-08 10:16:30');

    expect(RequestRollup::query()->where('grain', 'minute')->sum('requests'))->toBe(1)
        ->and(app(MinuteBuffer::class)->read('req', CarbonImmutable::parse('2026-10-08 10:15'))->isEmpty())->toBeTrue();
});

it('adds user rows into their hour with the status mix and peak per minute', function (): void {
    Ops::segmentUsing(fn (User $user): string => "role-{$user->getAuthIdentifier()}");

    $this->actingAs(actingUser(7))->getJson('api/things/1');
    $this->actingAs(actingUser(7))->getJson('api/limited');
    rollupAt('2026-10-08 10:16:05');

    $this->actingAs(actingUser(7))->getJson('api/limited');
    rollupAt('2026-10-08 10:17:05');

    $limited = UserRequestRollup::query()->where('route', 'api/limited')->sole();

    expect($limited)
        ->user_id->toBe('7')
        ->segment->toBe('role-7')
        ->requests->toBe(2)
        ->status_429->toBe(2)
        ->peak_per_minute->toBe(1)
        ->and(UserRequestRollup::query()->count())->toBe(2);
});

it('writes busy user × route minutes as a signal for the rate rule', function (): void {
    app(OpsSettings::class)->set('signals.user_route_floor', 3);

    foreach (range(1, 3) as $ignored) {
        $this->actingAs(actingUser(9))->getJson('api/things/1');
    }
    $this->actingAs(actingUser(10))->getJson('api/things/1');

    rollupAt('2026-10-08 10:16:05');

    expect(SignalRollup::query()->where('signal', BuiltInSignals::USER_ROUTE)->pluck('count', 'key')->all())
        ->toBe(['user:9|things.show' => 3]);
});

it('skips the dashboard, ignored paths and disabled recording', function (): void {
    $this->get('ops/home');
    app(OpsSettings::class)->set('recorders.requests.ignore', ['api/limited']);
    $this->getJson('api/limited');

    Ops::withoutRecording(fn () => $this->getJson('api/things/1'));

    rollupAt('2026-10-08 10:16:05');

    expect(RequestRollup::query()->count())->toBe(0);
});

it('never breaks the response when the buffer fails', function (): void {
    app()->instance(MinuteBuffer::class, new class implements MinuteBuffer
    {
        public function add(string $stream, DateTimeInterface $at, array $increments, array $maxima = [], array $members = []): void
        {
            throw new RuntimeException('redis is down');
        }

        public function read(string $stream, DateTimeInterface $minute): MinuteData
        {
            return new MinuteData;
        }

        public function forget(string $stream, DateTimeInterface $minute): void {}
    });
    app()->forgetInstance(RequestRecorder::class);

    $this->getJson('api/things/1')->assertOk();
});
