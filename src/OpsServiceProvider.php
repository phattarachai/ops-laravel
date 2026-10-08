<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel;

use Illuminate\Auth\Events\Failed;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Override;
use Phattarachai\OpsCore\Alerts\AlertEngine;
use Phattarachai\OpsCore\Alerts\RuleEvaluator;
use Phattarachai\OpsLaravel\Alerts\ChannelRegistry;
use Phattarachai\OpsLaravel\Alerts\DatabaseSignalReader;
use Phattarachai\OpsLaravel\Alerts\EloquentIncidentStore;
use Phattarachai\OpsLaravel\Buffer\ArrayMinuteBuffer;
use Phattarachai\OpsLaravel\Buffer\MinuteBuffer;
use Phattarachai\OpsLaravel\Buffer\RedisMinuteBuffer;
use Phattarachai\OpsLaravel\Console\AlertsCommand;
use Phattarachai\OpsLaravel\Console\DeployCommand;
use Phattarachai\OpsLaravel\Console\InstallCommand;
use Phattarachai\OpsLaravel\Console\PruneCommand;
use Phattarachai\OpsLaravel\Console\RollupCommand;
use Phattarachai\OpsLaravel\Http\Middleware\AuthorizeOps;
use Phattarachai\OpsLaravel\Http\Middleware\RecordRequests;
use Phattarachai\OpsLaravel\Recorders\CacheRecorder;
use Phattarachai\OpsLaravel\Recorders\ExceptionRecorder;
use Phattarachai\OpsLaravel\Recorders\FailedLoginRecorder;
use Phattarachai\OpsLaravel\Recorders\JobRecorder;
use Phattarachai\OpsLaravel\Recorders\OutgoingRecorder;
use Phattarachai\OpsLaravel\Recorders\QueryRecorder;
use Phattarachai\OpsLaravel\Recorders\RequestRecorder;
use Phattarachai\OpsLaravel\Recorders\ScheduleRecorder;
use Phattarachai\OpsLaravel\Recorders\SignalRecorder;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

final class OpsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ops.php', 'ops');

        $this->app->singleton(OpsSettings::class);
        $this->app->singleton(MinuteBuffer::class, fn (): MinuteBuffer => $this->buffer());
        $this->app->singleton(RequestRecorder::class);
        $this->app->singleton(OutgoingRecorder::class);
        $this->app->singleton(JobRecorder::class);
        $this->app->singleton(ScheduleRecorder::class);
        $this->app->singleton(SignalRecorder::class);
        $this->app->singleton(QueryRecorder::class);
        $this->app->singleton(CacheRecorder::class);
        $this->app->singleton(ChannelRegistry::class);
        $this->app->bind(AlertEngine::class, fn (): AlertEngine => new AlertEngine(new RuleEvaluator(new DatabaseSignalReader), new EloquentIncidentStore));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'ops');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ops');
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerMiddleware();
        $this->registerListeners();
        $this->registerSchedule();
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        if (! config('ops.ui.enabled', true) || $this->app->routesAreCached()) {
            return;
        }

        Route::prefix(trim((string) config('ops.path', 'ops'), '/'))
            ->middleware([...(array) config('ops.middleware', ['web']), AuthorizeOps::class])
            ->name('ops.')
            ->group(__DIR__.'/../routes/ui.php');
    }

    private function buffer(): MinuteBuffer
    {
        return match (config('ops.buffer.driver')) {
            'array' => new ArrayMinuteBuffer,
            default => new RedisMinuteBuffer(
                config('ops.buffer.connection'),
                (string) config('ops.buffer.prefix', 'ops'),
                (int) config('ops.buffer.ttl_seconds', 10800),
            ),
        };
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/ops.php' => config_path('ops.php')], 'ops-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'ops-migrations');
        $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/ops')], 'ops-lang');
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([InstallCommand::class, RollupCommand::class, AlertsCommand::class, PruneCommand::class, DeployCommand::class]);
    }

    private function registerMiddleware(): void
    {
        $kernel = $this->app->make(HttpKernel::class);

        if ($kernel instanceof Kernel && ! $kernel->hasMiddleware(RecordRequests::class)) {
            $kernel->prependMiddleware(RecordRequests::class);
        }
    }

    private function registerListeners(): void
    {
        Event::listen(fn (JobProcessing $event) => $this->app->make(JobRecorder::class)->started($event));
        Event::listen(fn (JobProcessed $event) => $this->app->make(JobRecorder::class)->succeeded($event));
        Event::listen(fn (JobExceptionOccurred $event) => $this->app->make(JobRecorder::class)->failed($event));
        Event::listen(fn (JobFailed $event) => $this->app->make(JobRecorder::class)->failed($event));
        Event::listen(fn (JobTimedOut $event) => $this->app->make(JobRecorder::class)->timedOut($event));

        Event::listen(fn (ScheduledTaskStarting $event) => $this->app->make(ScheduleRecorder::class)->starting($event));
        Event::listen(fn (ScheduledTaskFinished $event) => $this->app->make(ScheduleRecorder::class)->finished($event));
        Event::listen(fn (ScheduledTaskFailed $event) => $this->app->make(ScheduleRecorder::class)->failed($event));
        Event::listen(fn (ScheduledTaskSkipped $event) => $this->app->make(ScheduleRecorder::class)->skipped($event));
        Event::listen(fn (CommandStarting $event) => $this->app->make(ScheduleRecorder::class)->commandStarting($event));

        Event::listen(fn (ResponseReceived $event) => $this->app->make(OutgoingRecorder::class)->received($event));
        Event::listen(fn (ConnectionFailed $event) => $this->app->make(OutgoingRecorder::class)->failed($event));

        Event::listen(fn (Failed $event) => $this->app->make(FailedLoginRecorder::class)->handle($event));

        Event::listen(fn (QueryExecuted $event) => $this->app->make(QueryRecorder::class)->record($event));
        Event::listen(fn (CacheHit $event) => $this->app->make(CacheRecorder::class)->record($event));
        Event::listen(fn (CacheMissed $event) => $this->app->make(CacheRecorder::class)->record($event));
        Event::listen(fn (MessageLogged $event) => $this->app->make(ExceptionRecorder::class)->handle($event));
    }

    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (! config('ops.enabled') || ! config('ops.schedule')) {
                return;
            }

            $schedule->command('ops:rollup')->everyMinute()->withoutOverlapping();
            $schedule->command('ops:alerts')->everyMinute()->withoutOverlapping();
            $schedule->command('ops:prune')->dailyAt('03:10')->withoutOverlapping();
        });
    }
}
