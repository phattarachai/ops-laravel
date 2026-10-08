<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
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

it('registers the rollup and prune commands on the scheduler', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode(' ');

    expect($commands)->toContain('ops:rollup')->toContain('ops:prune');
});
