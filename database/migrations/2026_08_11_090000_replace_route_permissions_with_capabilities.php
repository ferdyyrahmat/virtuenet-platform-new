<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'admin.users.index' => ['view users', 'View users and their account details.'],
            'admin.users.create' => ['create users', 'Create user accounts.'],
            'admin.users.store' => ['create users', 'Create user accounts.'],
            'admin.users.edit' => ['edit users', 'Edit user accounts.'],
            'admin.users.update' => ['edit users', 'Edit user accounts.'],
            'admin.users.destroy' => ['delete users', 'Delete user accounts.'],
            'admin.permissions.index' => ['view roles and permissions', 'View roles and their assigned permissions.'],
            'admin.permissions.create' => ['create roles', 'Create roles and assign permissions.'],
            'admin.permissions.store' => ['create roles', 'Create roles and assign permissions.'],
            'admin.permissions.edit' => ['edit roles and permissions', 'Edit roles, users, and assigned permissions.'],
            'admin.permissions.update' => ['edit roles and permissions', 'Edit roles, users, and assigned permissions.'],
            'admin.permissions.destroy' => ['delete roles', 'Delete roles that are not locked.'],
            'admin.permissions.lock' => ['lock roles', 'Lock or unlock protected roles.'],
            'admin.notifications.index' => ['view notifications', 'View in-app notification management.'],
            'admin.notifications.send-blast' => ['send notification blasts', 'Send in-app notifications to multiple users.'],
            'admin.audit-logs.index' => ['view audit logs', 'View application activity logs.'],
            'admin.tickets.index' => ['view support tickets', 'View support tickets.'],
            'admin.tickets.show' => ['view support tickets', 'View support tickets.'],
            'admin.tickets.reply' => ['reply to support tickets', 'Reply to support tickets.'],
            'admin.tickets.assign' => ['assign support tickets', 'Assign support tickets to developers.'],
            'admin.tickets.destroy' => ['delete support tickets', 'Delete support tickets.'],
            'admin.tickets.developers.index' => ['view ticket developers', 'View developers available for ticket assignment.'],
            'admin.tickets.developers.store' => ['create ticket developers', 'Add developers for ticket assignment.'],
            'admin.tickets.developers.update' => ['edit ticket developers', 'Edit developers used for ticket assignment.'],
            'admin.tickets.developers.destroy' => ['delete ticket developers', 'Remove developers from ticket assignment.'],
            'admin.directory.index' => ['view directory', 'Browse files in the cloud directory.'],
            'admin.directory.upload' => ['upload directory files', 'Upload files to the cloud directory.'],
            'admin.directory.folder' => ['create directory folders', 'Create folders in the cloud directory.'],
            'admin.directory.download' => ['download directory files', 'Download files from the cloud directory.'],
            'admin.directory.destroy' => ['delete directory items', 'Delete files or folders from the cloud directory.'],
        ];

        foreach (collect($permissions)->unique(fn (array $permission) => $permission[0]) as [$name, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        foreach ($permissions as $routePermission => [$capability]) {
            $sourceId = DB::table('permissions')
                ->where('name', $routePermission)
                ->where('guard_name', 'web')
                ->value('id');
            $targetId = DB::table('permissions')
                ->where('name', $capability)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $sourceId || ! $targetId) {
                continue;
            }

            foreach (DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $targetId,
                    'role_id' => $roleId,
                ]);
            }

            foreach (DB::table('model_has_permissions')->where('permission_id', $sourceId)->get(['model_type', 'model_id']) as $model) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $targetId,
                    'model_type' => $model->model_type,
                    'model_id' => $model->model_id,
                ]);
            }

            DB::table('permissions')->where('id', $sourceId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Capability names are the canonical authorization values; route names are not restored.
    }
};
