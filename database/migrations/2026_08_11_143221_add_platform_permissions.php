<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'view service requests' => 'View the complete service request queue and request details.',
            'review service requests' => 'Approve, reject, or request revisions at the active approval stage.',
            'manage service requests' => 'Assign owners, update delivery status, and publish delivery details.',
            'view ai usage' => 'View organization AI spend, usage, budgets, and gateway logs.',
            'manage integrations' => 'Configure and test encrypted external gateway connections.',
            'view delivery tasks' => 'View GitHub issues and their Lark task synchronization state.',
            'sync delivery tasks' => 'Start GitHub to Lark task synchronization.',
        ];

        foreach ($permissions as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('name', array_keys($permissions))->pluck('id');
        $roleIds = DB::table('roles')->whereIn('name', ['Developer', 'Admin'])->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', [
            'view service requests',
            'review service requests',
            'manage service requests',
            'view ai usage',
            'manage integrations',
            'view delivery tasks',
            'sync delivery tasks',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
