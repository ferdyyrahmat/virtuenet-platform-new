<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpatiePermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spatie_permission_controls_an_admin_route(): void
    {
        $role = Role::create(['name' => 'Support', 'guard_name' => 'web']);
        $permission = Permission::findOrCreate('view audit logs', 'web');
        $role->givePermissionTo($permission);

        $allowed = User::factory()->create();
        $allowed->assignRole($role);

        $this->actingAs($allowed)
            ->get(route('admin.audit-logs.index'))
            ->assertOk();

        $denied = User::factory()->create();

        $this->actingAs($denied)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();
    }

    public function test_role_editor_shows_capabilities_instead_of_route_permissions(): void
    {
        $managerRole = Role::create(['name' => 'Access Manager', 'guard_name' => 'web']);
        $managerRole->givePermissionTo(Permission::findOrCreate('edit roles and permissions', 'web'));

        $manager = User::factory()->create();
        $manager->assignRole($managerRole);
        $targetRole = Role::create(['name' => 'Support', 'guard_name' => 'web']);

        $this->actingAs($manager)
            ->get(route('admin.permissions.edit', $targetRole->id))
            ->assertOk()
            ->assertSee('view audit logs')
            ->assertSee('Roles & Permissions')
            ->assertSee('Support Tickets')
            ->assertSee('Cloud Directory')
            ->assertSee('Guard')
            ->assertDontSee('Permission group')
            ->assertDontSee('admin.audit-logs.index');
    }
}
