<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Override;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\OpsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    #[Override]
    protected function tearDown(): void
    {
        Ops::flush();

        parent::tearDown();
    }

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [InertiaServiceProvider::class, OpsServiceProvider::class];
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('ops.buffer.driver', 'array');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('view.paths', [__DIR__.'/resources/views']);
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
    }
}
