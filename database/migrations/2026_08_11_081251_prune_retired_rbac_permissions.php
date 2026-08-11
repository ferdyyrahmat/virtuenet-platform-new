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
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')
            ->where(function ($query): void {
                foreach (['admin.backups.%', 'admin.database.%', 'admin.maintenance.%', 'admin.queues.%', 'admin.settings.branding.%', 'admin.settings.websocket.%'] as $pattern) {
                    $query->orWhere('route_name', 'like', $pattern);
                }

                $query->orWhereIn('route_name', [
                    'admin.notifications.settings.store',
                    'admin.notifications.test-connector',
                ]);
            })
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Retired permissions are intentionally not restored.
    }
};
