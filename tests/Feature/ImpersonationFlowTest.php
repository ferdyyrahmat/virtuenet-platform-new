<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_return_from_an_impersonated_session(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole(Role::create(['name' => 'Developer', 'guard_name' => 'web']));
        $impersonated = User::factory()->create();

        $this->actingAs($impersonated)
            ->withSession(['impersonation' => ['original_user_id' => $developer->id]])
            ->post(route('impersonation.stop'))
            ->assertRedirect(route('v1.dashboard'));

        $this->assertAuthenticatedAs($developer);
    }
}
