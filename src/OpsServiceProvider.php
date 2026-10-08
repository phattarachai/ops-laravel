<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel;

use Illuminate\Support\ServiceProvider;

final class OpsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ops.php', 'ops');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ops.php' => config_path('ops.php'),
            ], 'ops-config');
        }
    }
}
