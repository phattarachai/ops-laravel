<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Phattarachai\OpsLaravel\Ops;

beforeEach(fn () => Ops::flushAuth());
afterEach(fn () => Ops::flushAuth());

it('allows every request in the local environment', function () {
    $this->app['env'] = 'local';

    expect(Ops::check(Request::create('/ops')))->toBeTrue();
});

it('denies outside local when neither a callback nor a viewOps gate is registered', function () {
    $this->app['env'] = 'production';

    expect(Ops::check(Request::create('/ops')))->toBeFalse();
});

it('lets the registered callback decide outside local, ahead of the gate', function () {
    $this->app['env'] = 'production';
    Gate::define('viewOps', fn (?User $user = null) => true);

    Ops::auth(fn (Request $request) => $request->header('X-Ops') === 'yes');

    expect(Ops::check(Request::create('/ops')))->toBeFalse()
        ->and(Ops::check(Request::create('/ops', server: ['HTTP_X_OPS' => 'yes'])))->toBeTrue();
});

it('falls back to the viewOps gate outside local', function () {
    $this->app['env'] = 'production';
    Gate::define('viewOps', fn (?User $user = null) => true);

    expect(Ops::check(Request::create('/ops')))->toBeTrue();
});
