<?php

namespace Database\Seeders;

use App\Models\Developer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Level 1 Role: Developer (Highest Authority)
        $devRole = Role::firstOrCreate(
            ['name' => 'Developer'],
            [
                'description' => 'Level 1 Supreme Authority: Full system access, developer tools, task queues, Redis, and ticket resolution.',
                'guard_name' => 'web',
                'is_locked' => true,
            ]
        );

        // 2. Create Level 2 Role: Admin (Management Authority)
        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin'],
            [
                'description' => 'Level 2 Management Authority: Manage users, review tickets, view audit trails, and manage system feedbacks.',
                'guard_name' => 'web',
            ]
        );

        // 3. Create Level 3 Role: User (Standard Account)
        $userRole = Role::firstOrCreate(
            ['name' => 'User'],
            [
                'description' => 'Level 3 Standard Account: Registered user with access to user dashboard, profile, and ticket submissions.',
                'guard_name' => 'web',
            ]
        );

        // Auto-seed permissions from registered admin routes to Developer and Admin roles
        $adminAllowedGroups = ['users', 'tickets', 'feedbacks', 'audit-logs', 'requests', 'connections', 'github-tasks', 'ai-usage'];

        foreach (Route::getRoutes() as $r) {
            $name = $r->getName();
            if ($name && str_starts_with($name, 'admin.')) {
                $parts = explode('.', $name);

                foreach ($this->routePermissions($r->gatherMiddleware()) as $permissionName) {
                    $permission = Permission::findOrCreate($permissionName, 'web');
                    $devRole->givePermissionTo($permission);

                    if (isset($parts[1]) && in_array($parts[1], $adminAllowedGroups, true)) {
                        $adminRole->givePermissionTo($permission);
                    }
                }
            }
        }

        // Create or update supreme Developer user (Ferdy Rahmat)
        $devUser = User::updateOrCreate(
            ['email' => 'ferdyyrahmat@gmail.com'],
            [
                'name' => 'Ferdy Rahmat',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign Developer role
        $devUser->assignRole($devRole);

        // Also ensure Developer is registered in developers table for ticket assignment & alerts
        Developer::updateOrCreate(
            ['email' => $devUser->email],
            [
                'user_id' => $devUser->id,
                'name' => $devUser->name,
                'phone' => $devUser->phone ?? '6289524424936',
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Hierarchical Roles (Developer > Admin > User) & Developer User seeded cleanly!');
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
