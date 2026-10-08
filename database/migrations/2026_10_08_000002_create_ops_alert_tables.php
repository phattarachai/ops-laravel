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

        if (! $schema->hasTable('ops_rules')) {
            $schema->create('ops_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->nullable()->unique();
                $table->string('name');
                $table->string('type', 20);
                $table->string('signal', 100);
                $table->double('threshold')->default(0);
                $table->unsignedInteger('window_seconds')->default(300);
                $table->string('severity', 20)->default('warning');
                $table->string('denominator_signal', 100)->nullable();
                $table->unsignedInteger('min_samples')->default(1);
                $table->string('distinct_field', 64)->nullable();
                $table->json('keys')->nullable();
                $table->string('min_level', 20)->default('warning');
                $table->unsignedInteger('cooldown_seconds')->default(900);
                $table->unsignedInteger('resolve_after_seconds')->default(300);
                $table->json('environments')->nullable();
                $table->json('channels')->nullable();
                $table->boolean('active')->default(true);
                $table->boolean('notify_on_resolve')->default(true);
                $table->timestamp('muted_until')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('ops_incidents')) {
            $schema->create('ops_incidents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('rule_id');
                $table->string('key');
                $table->string('status', 20);
                $table->string('severity', 20);
                $table->timestamp('opened_at');
                $table->timestamp('last_breached_at');
                $table->timestamp('last_notified_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->double('peak_value')->default(0);
                $table->double('last_value')->default(0);
                $table->unsignedInteger('notifications')->default(0);
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index(['rule_id', 'status']);
                $table->index(['status', 'opened_at']);
            });
        }

        if (! $schema->hasTable('ops_notifications_sent')) {
            $schema->create('ops_notifications_sent', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('incident_id')->nullable()->index();
                $table->foreignId('rule_id')->nullable();
                $table->string('channel', 40);
                $table->string('kind', 20);
                $table->boolean('delivered');
                $table->text('error')->nullable();
                $table->timestamp('sent_at')->index();
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty, as in the telemetry migration.
    }

    private function schema(): Builder
    {
        return Schema::connection(config('ops.connection'));
    }
};
