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
            ['name' => 'Developer'],
            [
                'description' => 'Full system access and developer tools.',
                'guard_name' => 'web',
                'is_locked' => true,
            ]
        );

        $adminRole = Role::updateOrCreate(
            ['name' => 'Admin'],
            [
                'description' => 'Management access for users, tickets, audit trails, and operations.',
                'guard_name' => 'web',
            ]
        );

        $userRole = Role::updateOrCreate(
            ['name' => 'User'],
            [
                'description' => 'Standard user access for profile and support tickets.',
                'guard_name' => 'web',
            ]
        );

        $adminGroups = ['users', 'tickets', 'feedbacks', 'audit-logs', 'requests', 'connections', 'github-tasks', 'ai-usage'];
        $developerPermissionNames = [];
        $adminPermissionNames = [];

        foreach (Route::getRoutes() as $route) {
            $routeName = $route->getName();
            if (! $routeName || ! str_starts_with($routeName, 'admin.')) {
                continue;
            }

            $group = explode('.', $routeName)[1] ?? 'system';
            foreach ($this->routePermissions($route->gatherMiddleware()) as $permissionName) {
                Permission::findOrCreate($permissionName, 'web');
                $developerPermissionNames[] = $permissionName;

                if (in_array($group, $adminGroups, true)) {
                    $adminPermissionNames[] = $permissionName;
                }
            }
        }

        $developerRole->syncPermissions(array_unique($developerPermissionNames));
        $adminRole->syncPermissions(array_unique($adminPermissionNames));
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

    private function routePermissions(array $middleware): array
    {
        return collect($middleware)
            ->filter(fn ($name): bool => is_string($name) && str_starts_with($name, 'permission:'))
            ->flatMap(fn (string $name): array => explode('|', substr($name, 11)))
            ->filter()
            ->values()
            ->all();
    }
}
