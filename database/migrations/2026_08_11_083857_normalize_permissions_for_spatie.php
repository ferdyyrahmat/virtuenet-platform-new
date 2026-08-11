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
        if (! Schema::hasTable('permissions') || ! Schema::hasColumn('permissions', 'route_name')) {
            return;
        }

        DB::table('permissions')->whereNotNull('route_name')->orderBy('id')->get()->each(function ($permission): void {
            DB::table('permissions')->where('id', $permission->id)->update([
                'name' => $permission->route_name,
            ]);
        });

        Schema::table('permissions', function ($table): void {
            $table->dropUnique('permissions_route_name_unique');
            $table->dropColumn(['route_name', 'group_name']);
            $table->unique(['name', 'guard_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Permission names are the canonical Spatie values and are not reverted.
    }
};
