<?php

namespace Tests\Feature\Seeder;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_roles_demo_users_and_route_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => Role::DEVELOPER, 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => Role::ADMIN, 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => Role::USER, 'guard_name' => 'web']);

        $developer = User::where('email', 'developer@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $user = User::where('email', 'user@example.com')->firstOrFail();

        $this->assertTrue($developer->hasRole(Role::DEVELOPER));
        $this->assertTrue($admin->hasRole(Role::ADMIN));
        $this->assertTrue($user->hasRole(Role::USER));

        $this->assertDatabaseHas('permissions', ['name' => 'admin.users.index', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'admin.tickets.index', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'admin.feedbacks.index', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'admin.audit-logs.index', 'guard_name' => 'web']);

        $devRole = Role::where('name', Role::DEVELOPER)->firstOrFail();
        $adminRole = Role::where('name', Role::ADMIN)->firstOrFail();
        $userRole = Role::where('name', Role::USER)->firstOrFail();

        $this->assertTrue($devRole->hasPermissionTo('admin.permissions.index'));
        $this->assertTrue($devRole->hasPermissionTo('admin.users.index'));
        $this->assertTrue($adminRole->hasPermissionTo('admin.users.index'));
        $this->assertTrue($adminRole->hasPermissionTo('admin.tickets.index'));
        $this->assertTrue($adminRole->hasPermissionTo('admin.feedbacks.index'));
        $this->assertTrue($adminRole->hasPermissionTo('admin.audit-logs.index'));
        $this->assertFalse($adminRole->hasPermissionTo('admin.permissions.index'));
        $this->assertFalse($userRole->hasPermissionTo('admin.users.index'));

        $this->assertDatabaseHas('developers', [
            'user_id' => $developer->id,
            'email' => 'developer@example.com',
            'is_active' => true,
        ]);
    }
}
