<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsLaravel\Alerts\DefaultRules;
use Phattarachai\OpsLaravel\Models\RequestRollup;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Models\UserRequestRollup;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Ui\Format;

beforeEach(function (): void {
    Ops::auth(fn (Request $request): bool => true);
    Ops::resolveUsersUsing(fn (array $ids): array => collect($ids)
        ->filter(fn (string $id): bool => $id === '7')
        ->mapWithKeys(fn (string $id): array => [$id => ['name' => 'Flooding Support', 'detail' => 'T165']])
        ->all());
    Route::get('api/chat-rooms/{room}', fn (): string => 'ok')->name('chat-rooms.show');
    app('router')->getRoutes()->refreshNameLookups();
    $this->travelTo(CarbonImmutable::today()->setTime(12, 30));

    seedRequestRollups('chat-rooms.show');
    seedRequestRollups('unmatched');
    seedRequestRollups('api/os/catalog/{product}');
    seedUserRollup('chat-rooms.show', '7', ['segment' => 'support', 'requests' => 312, 'status_429' => 300, 'sum_ms' => 3000, 'peak_per_minute' => 180]);
});

function seedRequestRollups(string $route): void
{
    $histogram = json_encode(array_replace(LatencyHistogram::empty(), [1 => 10, 2 => 2]));
    $row = ['route' => $route, 'method' => 'GET', 'segment' => 'support', 'sum_ms' => 3000, 'max_ms' => 450, 'histogram' => $histogram];
    $now = CarbonImmutable::now();

    foreach (['minute' => $now->subMinutes(10)->startOfMinute(), 'hour' => $now->startOfHour()] as $grain => $bucket) {
        RequestRollup::query()->insert([
            [...$row, 'grain' => $grain, 'bucket' => $bucket, 'status' => 200, 'requests' => 12],
            [...$row, 'grain' => $grain, 'bucket' => $bucket, 'status' => 429, 'requests' => 300],
        ]);
    }
}

/** @param  array<string, int|string>  $values */
function seedUserRollup(string $route, string $userId, array $values): void
{
    UserRequestRollup::query()->insert(['bucket' => CarbonImmutable::now()->startOfHour(), 'route' => $route, 'user_id' => $userId, ...$values]);
}

it('lists routes on the board with their 429s, flags unmatched as noise and links the detail with the window', function (): void {
    RequestRollup::query()->insert([
        'grain' => 'minute', 'bucket' => CarbonImmutable::now()->subWeek()->subMinutes(10)->startOfMinute(), 'route' => 'chat-rooms.show',
        'method' => 'GET', 'status' => 200, 'segment' => 'support', 'requests' => 156, 'sum_ms' => 100, 'max_ms' => 10,
    ]);
    $token = Format::base64url('chat-rooms.show');

    $this->get(route('ops.requests', ['window' => '6h']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'requests')
            ->where('tab', 'routes')
            ->where('recording', true)
            ->where('threshold', 120)
            ->where('summary.requests', 936)
            ->where('summary.s429', 900)
            ->where('summary.users', 1)
            ->where('summary.last_week', 156)
            ->where('routes', function ($routes) use ($token): bool {
                $routes = collect($routes)->keyBy('route');

                expect($routes['chat-rooms.show'])->toMatchArray([
                    's429' => 300,
                    'methods' => 'GET',
                    'uri' => '/api/chat-rooms/{room}',
                    'group' => 'chat-rooms',
                    'noise' => false,
                    'last_week' => 156,
                    'delta' => 100,
                    'url' => route('ops.requests.show', ['token' => $token, 'window' => '6h']),
                ])
                    ->and(collect($routes['chat-rooms.show']['spark'])->sum('s429'))->toBe(300)
                    ->and($routes['unmatched']['noise'])->toBeTrue()
                    ->and($routes['unmatched']['uri'])->toBeNull()
                    ->and($routes['api/os/catalog/{product}'])->toMatchArray(['group' => 'os', 'uri' => '/api/os/catalog/{product}', 'delta' => null]);

                return true;
            })
            ->where('hot_users.0.name', 'Flooding Support')
            ->where('hot_users.0.peak_per_minute', 180)
            ->where('hot_users.0.route_url', route('ops.requests.show', ['token' => $token, 'user' => '7', 'window' => '6h']))
            ->where('users', [])
            ->where('selected_user', null));
});

it('reads the hot-user threshold from the user-route-flood rule and hides the list below it', function (): void {
    DefaultRules::seed();
    Rule::query()->where('slug', 'user-route-flood')->update(['threshold' => 200]);

    $this->get(route('ops.requests'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('threshold', 200)
            ->where('hot_users', []));
});

it('lists the top users and filters them by segment', function (): void {
    seedUserRollup('chat-rooms.show', '9', ['segment' => 'sales', 'requests' => 5, 'sum_ms' => 50, 'peak_per_minute' => 2]);

    $this->get(route('ops.requests', ['tab' => 'users']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('routes', [])
            ->where('segment_options', ['sales', 'support'])
            ->where('users.0.name', 'Flooding Support')
            ->where('users.0.detail', 'T165')
            ->where('users.0.segment', 'support')
            ->where('users.0.s429', 300)
            ->where('users.1.name', '#9'));

    $this->get(route('ops.requests', ['tab' => 'users', 'segment' => 'sales']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('user_filters.segment', 'sales')
            ->has('users', 1)
            ->where('users.0.user_id', '9'));
});

it('ignores unknown or malformed segment values', function (mixed $segment): void {
    $this->get(route('ops.requests', ['tab' => 'users', 'segment' => $segment]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('user_filters', ['segment' => null])
            ->has('users', 1));
})->with(['bogus', [['support']]]);

it('opens the user panel with hourly traffic and routes, scoped to the route on the detail', function (): void {
    $this->get(route('ops.requests', ['user' => '7']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selected_user.name', 'Flooding Support')
            ->where('selected_user.segment', 'support')
            ->where('selected_user.scope', null)
            ->where('selected_user.peak_per_minute', 180)
            ->where('selected_user.hours', fn ($hours): bool => collect($hours)->sum('value') === 312)
            ->where('selected_user.routes.0.route', 'chat-rooms.show')
            ->where('selected_user.routes.0.uri', '/api/chat-rooms/{room}'));

    $this->get(route('ops.requests.show', ['token' => Format::base64url('chat-rooms.show'), 'user' => '7']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('selected_user.scope', 'chat-rooms.show'));
});

it('renders the route detail with status bars, segment split, histogram, top users and today against last week', function (): void {
    $closedHour = ['grain' => 'hour', 'route' => 'chat-rooms.show', 'method' => 'GET', 'status' => 200, 'segment' => 'guest', 'sum_ms' => 100, 'max_ms' => 10];
    RequestRollup::query()->insert([
        [...$closedHour, 'bucket' => CarbonImmutable::today()->setTime(9, 0), 'requests' => 40],
        [...$closedHour, 'bucket' => CarbonImmutable::today()->subWeek()->setTime(9, 0), 'requests' => 20],
    ]);

    $this->get(route('ops.requests.show', ['token' => Format::base64url('chat-rooms.show'), 'window' => '6h']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'requests.show')
            ->where('route.name', 'chat-rooms.show')
            ->where('route.uri', '/api/chat-rooms/{room}')
            ->where('route.methods', 'GET')
            ->where('summary.requests', 312)
            ->where('today', ['today' => 40, 'last_week' => 20, 'delta' => 100])
            ->where('buckets', fn ($buckets): bool => collect($buckets)->sum('s429') === 300 && collect($buckets)->sum('ok') === 12)
            ->where('segment_split.0.segment', 'support')
            ->where('segment_split.0.s429', 300)
            ->has('histogram', LatencyHistogram::BIN_COUNT)
            ->where('histogram.1.count', 20)
            ->where('users.0.name', 'Flooding Support')
            ->where('back_url', route('ops.requests', ['window' => '6h'])));
});

it('answers 404 for a route token without hour rows or that does not decode', function (string $token): void {
    $this->get(route('ops.requests.show', ['token' => $token]))->assertNotFound();
})->with([
    'unknown route' => Format::base64url('nope.never'),
    'malformed' => '%%%',
]);

it('says so when request recording is off', function (): void {
    config(['ops.recorders.requests.enabled' => false]);

    $this->get(route('ops.requests'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('recording', false));
});
