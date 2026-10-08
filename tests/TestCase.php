<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Phattarachai\OpsLaravel\Facades\Ops;
use Phattarachai\OpsLaravel\OpsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [OpsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['Ops' => Ops::class];
    }
}
