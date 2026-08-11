<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->orderBy('id')->chunkById(500, function ($logs): void {
            foreach ($logs as $log) {
                DB::table(config('activitylog.table_name', 'activity_log'))->insert([
                    'log_name' => $log->module ?: 'system',
                    'description' => $log->action_description,
                    'subject_type' => null,
                    'subject_id' => null,
                    'causer_type' => $log->user_id ? App\Models\User::class : null,
                    'causer_id' => $log->user_id,
                    'event' => $log->event,
                    'properties' => json_encode(array_filter([
                        'legacy_user_name' => $log->user_name,
                        'ip_address' => $log->ip_address,
                        'user_agent' => $log->user_agent,
                        'legacy_properties' => $log->properties ? json_decode($log->properties, true) : null,
                    ], static fn ($value) => $value !== null), JSON_THROW_ON_ERROR),
                    'created_at' => $log->created_at,
                    'updated_at' => $log->updated_at,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Legacy rows are intentionally not recreated after the forward migration.
    }
};
