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

        $this->createUnlessExists($schema, 'ops_slow_queries', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('bucket');
            $table->string('hash', 40);
            $table->string('connection', 64);
            $table->text('sql');
            $table->string('location')->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->unsignedBigInteger('sum_ms')->default(0);
            $table->unsignedInteger('max_ms')->default(0);
            $table->timestamps();

            $table->unique(['bucket', 'hash']);
            $table->index('bucket');
        });

        $this->createUnlessExists($schema, 'ops_cache_rollups', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('bucket');
            $table->string('key');
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('misses')->default(0);
            $table->timestamps();

            $table->unique(['bucket', 'key']);
            $table->index('bucket');
        });

        $this->createUnlessExists($schema, 'ops_queue_samples', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('bucket');
            $table->string('connection', 64);
            $table->string('queue');
            $table->unsignedInteger('size')->default(0);
            $table->unsignedInteger('pending')->nullable();
            $table->unsignedInteger('delayed')->nullable();
            $table->unsignedInteger('reserved')->nullable();
            $table->unsignedInteger('oldest_wait_seconds')->nullable();

            $table->unique(['bucket', 'connection', 'queue']);
            $table->index('bucket');
        });

        $this->createUnlessExists($schema, 'ops_system_samples', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('bucket');
            $table->string('host', 100);
            $table->double('load_1')->nullable();
            $table->unsignedSmallInteger('cpus')->nullable();
            $table->double('memory_used_pct')->nullable();
            $table->double('disk_used_pct')->nullable();

            $table->unique(['bucket', 'host']);
            $table->index('bucket');
        });

        $this->createUnlessExists($schema, 'ops_deploys', function (Blueprint $table): void {
            $table->id();
            $table->string('release', 100);
            $table->string('description')->nullable();
            $table->timestamp('deployed_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Intentionally empty, as in the telemetry migration.
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
