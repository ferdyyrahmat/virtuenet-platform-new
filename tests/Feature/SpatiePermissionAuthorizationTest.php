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
        $permission = Permission::create(['name' => 'admin.audit-logs.index', 'guard_name' => 'web']);
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
}
