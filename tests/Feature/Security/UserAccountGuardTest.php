<?php

namespace Tests\Feature\Security;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccountGuardTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithPermissions(): User
    {
        $role = Role::firstOrCreate(['name' => Role::ADMIN, 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.users.destroy', 'guard_name' => 'web']));
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.users.update', 'guard_name' => 'web']));

        $admin = User::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    private function developerUser(): User
    {
        $role = Role::firstOrCreate(['name' => Role::DEVELOPER, 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.users.destroy', 'guard_name' => 'web']));
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.users.update', 'guard_name' => 'web']));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_cannot_delete_a_developer_user(): void
    {
        $admin = $this->adminWithPermissions();
        $developer = $this->developerUser();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $developer->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $developer->id]);
    }

    public function test_any_user_cannot_delete_own_account(): void
    {
        $admin = $this->adminWithPermissions();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_developer_can_delete_another_developer_when_one_remains(): void
    {
        $actor = $this->developerUser();
        $target = $this->developerUser();

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $target->id))
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_update_a_developer_user(): void
    {
        $admin = $this->adminWithPermissions();
        $developer = $this->developerUser();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $developer->id), [
                'name' => 'Hacked Name',
                'email' => 'hacked@example.com',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $developer->id, 'name' => $developer->name]);
    }

    public function test_developer_can_delete_regular_user(): void
    {
        $actor = $this->developerUser();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $target->id))
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }
}