<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Phattarachai\OpsLaravel\Facades\Ops as OpsFacade;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\OpsServiceProvider;

it('boots the service provider', function () {
    expect($this->app->getProvider(OpsServiceProvider::class))->toBeInstanceOf(OpsServiceProvider::class);
});

it('merges the package config defaults', function () {
    expect(config('ops.enabled'))->toBeTrue()
        ->and(config('ops.connection'))->toBeNull()
        ->and(config('ops.path'))->toBe('ops');
});

it('publishes the config under the ops-config tag', function () {
    $published = ServiceProvider::pathsToPublish(OpsServiceProvider::class, 'ops-config');

    expect($published)->toHaveCount(1)
        ->and(array_values($published)[0])->toBe(config_path('ops.php'));
});

it('resolves the Ops facade to the registration surface', function () {
    expect(OpsFacade::getFacadeRoot())->toBeInstanceOf(Ops::class);
});
