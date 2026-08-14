<?php

namespace Tests\Feature\UX;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.users.index', 'guard_name' => 'web']));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_sidebar_does_not_show_empty_infrastructure_menu(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $html = $this->actingAs($admin)
            ->get(route('v1.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('sidebarInfrastructure', $html);
    }

    public function test_developer_sidebar_shows_infrastructure_menu(): void
    {
        $developer = $this->userWithRole(Role::DEVELOPER);

        $html = $this->actingAs($developer)
            ->get(route('v1.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sidebarInfrastructure', $html);
    }

    public function test_admin_sidebar_shows_feedback_link(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.feedbacks.index', 'guard_name' => 'web']));

        $html = $this->actingAs($admin)
            ->get(route('v1.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('admin.feedbacks.index'), $html);
    }
}