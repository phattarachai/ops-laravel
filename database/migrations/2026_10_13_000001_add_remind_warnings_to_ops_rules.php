<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `remind_warnings`: off = a warning incident notifies on open and resolve only. The seeded
 * schedule-health rule turns it off, since a missed tick stays in the 24 h window for a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = $this->schema();

        if (! $schema->hasTable('ops_rules') || $schema->hasColumn('ops_rules', 'remind_warnings')) {
            return;
        }

        $schema->table('ops_rules', function (Blueprint $table): void {
            $table->boolean('remind_warnings')->default(true)->after('notify_on_resolve');
        });

        DB::connection(config('ops.connection'))->table('ops_rules')->where('slug', 'schedule-health')->update(['remind_warnings' => false]);
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
