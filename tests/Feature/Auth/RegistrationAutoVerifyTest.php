<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationAutoVerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_user_is_automatically_verified(): void
    {
        $this->post('/registering', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ])->assertJson(['success' => true]);

        $user = User::where('email', 'newuser@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_legacy_verification_routes_are_removed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertNotFound();

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertNotFound();
    }

    public function test_user_model_is_not_forced_to_verify(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertFalse($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail);
    }
}