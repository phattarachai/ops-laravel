<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = $this->schema();

        $this->createUnlessExists($schema, 'ops_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->createUnlessExists($schema, 'ops_http_rollups', function (Blueprint $table): void {
            $table->id();
            $table->string('grain', 10);
            $table->timestamp('bucket');
            $table->string('route');
            $table->string('method', 10);
            $table->unsignedSmallInteger('status');
            $table->string('segment', 64)->default('');
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedBigInteger('sum_ms')->default(0);
            $table->unsignedInteger('max_ms')->default(0);
            $table->unsignedInteger('p50_ms')->nullable();
            $table->unsignedInteger('p95_ms')->nullable();
            $table->json('histogram')->nullable();
            $table->timestamps();

            $table->unique(['grain', 'bucket', 'route', 'method', 'status', 'segment'], 'ops_http_rollups_key');
            $table->index(['grain', 'route', 'bucket']);
            $table->index(['grain', 'bucket']);
        });

        $this->createUnlessExists($schema, 'ops_http_user_rollups', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('bucket');
            $table->string('route');
            $table->string('user_id', 64);
            $table->string('segment', 64)->default('');
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('status_4xx')->default(0);
            $table->unsignedInteger('status_429')->default(0);
            $table->unsignedInteger('status_5xx')->default(0);
            $table->unsignedBigInteger('sum_ms')->default(0);
            $table->unsignedInteger('peak_per_minute')->default(0);
            $table->timestamps();

            $table->unique(['bucket', 'route', 'user_id']);
            $table->index(['route', 'bucket']);
            $table->index(['user_id', 'bucket']);
        });

        $this->createUnlessExists($schema, 'ops_outgoing_rollups', function (Blueprint $table): void {
            $table->id();
            $table->string('grain', 10);
            $table->timestamp('bucket');
            $table->string('endpoint');
            $table->string('method', 10);
            $table->unsignedSmallInteger('status');
            $table->string('outcome', 20);
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedInteger('timed')->default(0);
            $table->unsignedBigInteger('sum_ms')->default(0);
            $table->unsignedInteger('max_ms')->default(0);
            $table->unsignedInteger('p50_ms')->nullable();
            $table->unsignedInteger('p95_ms')->nullable();
            $table->json('histogram')->nullable();
            $table->timestamps();

            $table->unique(['grain', 'bucket', 'endpoint', 'method', 'status', 'outcome'], 'ops_outgoing_rollups_key');
            $table->index(['grain', 'endpoint', 'bucket']);
            $table->index(['grain', 'bucket']);
        });

        $this->createUnlessExists($schema, 'ops_job_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable()->index();
            $table->string('job_class');
            $table->string('queue')->nullable();
            $table->string('connection')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->string('status', 20);
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('wait_ms')->nullable();
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();
            $table->string('domain_type')->nullable();
            $table->string('domain_id')->nullable();
            $table->string('origin')->nullable();
            $table->string('parent_uuid')->nullable()->index();
            $table->unsignedBigInteger('schedule_run_id')->nullable()->index();
            $table->timestamps();

            $table->index(['job_class', 'started_at']);
            $table->index(['queue', 'started_at']);
            $table->index(['status', 'started_at']);
            $table->index(['domain_type', 'domain_id']);
        });

        $this->createUnlessExists($schema, 'ops_schedule_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('task');
            $table->string('expression');
            $table->string('status', 20);
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();
            $table->timestamps();

            $table->index(['task', 'started_at']);
            $table->index(['status', 'started_at']);
        });

        $this->createUnlessExists($schema, 'ops_signal_rollups', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('bucket');
            $table->string('signal', 100);
            $table->string('key');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['bucket', 'signal', 'key']);
            $table->index(['signal', 'bucket']);
        });

        $this->createUnlessExists($schema, 'ops_signal_values', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('bucket');
            $table->string('signal', 100);
            $table->string('key');
            $table->string('field', 64);
            $table->string('value');

            $table->unique(['bucket', 'signal', 'key', 'field', 'value'], 'ops_signal_values_key');
            $table->index(['signal', 'field', 'bucket']);
        });

        $this->createUnlessExists($schema, 'ops_rollup_ledger', function (Blueprint $table): void {
            $table->id();
            $table->string('stream', 20);
            $table->timestamp('bucket');
            $table->timestamp('created_at')->nullable();

            $table->unique(['stream', 'bucket']);
        });

        $this->createUnlessExists($schema, 'ops_probe_states', function (Blueprint $table): void {
            $table->id();
            $table->string('signal', 100);
            $table->string('key');
            $table->string('level', 20);
            $table->double('value')->nullable();
            $table->string('message', 500)->nullable();
            $table->timestamp('observed_at');
            $table->timestamps();

            $table->unique(['signal', 'key']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: the tables may predate this package on the host (see README "Adopting existing tables").
    }

    private function schema(): Builder
    {
        return Schema::connection(config('ops.connection'));
    }

    private function createUnlessExists(Builder $schema, string $table, Closure $definition): void
    {
        if ($schema->hasTable($table)) {
            return;
        }

        $schema->create($table, $definition);
    }
};
