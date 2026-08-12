<?php

namespace Database\Seeders;

use App\Models\Developer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

class RoleAndUserSeeder extends Seeder
{
    public function run(): void
    {
        $developerRole = Role::updateOrCreate(
            ['name' => Role::DEVELOPER],
            [
                'description' => 'Full system access and developer tools.',
                'guard_name' => 'web',
                'is_locked' => true,
            ]
        );

        $adminRole = Role::updateOrCreate(
            ['name' => Role::ADMIN],
            [
                'description' => 'Management access for users, tickets, audit trails, and operations.',
                'guard_name' => 'web',
            ]
        );

        $userRole = Role::updateOrCreate(
            ['name' => Role::USER],
            [
                'description' => 'Standard user access for profile and support tickets.',
                'guard_name' => 'web',
            ]
        );

        $adminGroups = ['users', 'tickets', 'feedbacks', 'audit-logs'];
        $developerPermissionNames = [];
        $adminPermissionNames = [];

        foreach (Route::getRoutes() as $route) {
            $routeName = $route->getName();
            if (!$routeName || !str_starts_with($routeName, 'admin.')) {
                continue;
            }

            $group = explode('.', $routeName)[1] ?? 'system';
            Permission::findOrCreate($routeName, 'web');

            $developerPermissionNames[] = $routeName;
            if (in_array($group, $adminGroups, true)) {
                $adminPermissionNames[] = $routeName;
            }
        }

        $developerRole->syncPermissions($developerPermissionNames);
        $adminRole->syncPermissions($adminPermissionNames);
        $userRole->syncPermissions([]);

        $developer = $this->user('Demo Developer', 'developer@example.com', $developerRole);
        $this->user('Demo Administrator', 'admin@example.com', $adminRole);
        $this->user('Demo User', 'user@example.com', $userRole);

        Developer::updateOrCreate(
            ['user_id' => $developer->id],
            [
                'name' => $developer->name,
                'email' => $developer->email,
                'is_active' => true,
            ]
        );

        $this->command?->info('Seeded Developer, Admin, User roles and 3 dummy users.');
    }

    private function user(string $name, string $email, Role $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles([$role]);
        return $user;
    }
}
