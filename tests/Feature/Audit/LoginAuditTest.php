<?php

namespace Tests\Feature\Audit;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LoginAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_is_audited(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'auth.login',
            'causer_id' => $user->id,
            'description' => 'User logged in',
        ]);
    }

    public function test_lark_login_is_audited_via_session_marker(): void
    {
        $user = User::factory()->create();

        $this->session(['auth_via' => 'lark']);

        Event::dispatch(new Login('web', $user, false));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'auth.login',
            'causer_id' => $user->id,
            'description' => 'User logged in via Lark SSO',
        ]);

        $this->assertNull(session('auth_via'));
    }

    public function test_impersonation_start_and_stop_are_audited(): void
    {
        $developerRole = Role::firstOrCreate(['name' => Role::DEVELOPER, 'guard_name' => 'web']);
        $developer = User::factory()->create();
        $developer->assignRole($developerRole);

        $target = User::factory()->create();

        $this->actingAs($developer)
            ->post(route('impersonation.start', $target))
            ->assertRedirect(route('v1.dashboard'));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'auth.impersonate',
            'description' => 'Impersonation started',
            'causer_id' => $developer->id,
        ]);

        $this->assertDatabaseMissing('activity_log', [
            'event' => 'auth.login',
            'causer_id' => $target->id,
        ]);

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('v1.dashboard'));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'auth.impersonate',
            'description' => 'Impersonation ended',
            'causer_id' => $developer->id,
        ]);
    }

    public function test_non_developer_cannot_start_impersonation(): void
    {
        $adminRole = Role::firstOrCreate(['name' => Role::ADMIN, 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('impersonation.start', $target))
            ->assertForbidden();

        $this->assertDatabaseMissing('activity_log', ['event' => 'auth.impersonate']);
    }
}