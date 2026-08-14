<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockscreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_lock_and_see_lockscreen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('lockscreen.lock'))
            ->assertRedirect(route('lockscreen'));

        $this->actingAs($user)
            ->get(route('lockscreen'))
            ->assertOk();
    }

    public function test_user_can_unlock_with_correct_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)->post(route('lockscreen.lock'));

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('lockscreen.unlock'), ['password' => 'password'])
            ->assertJson(['success' => true]);

        $this->assertFalse(session('user_is_locked'));
    }

    public function test_user_cannot_unlock_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->session(['user_is_locked' => true]);

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('lockscreen.unlock'), ['password' => 'wrong-password'])
            ->assertStatus(422);

        $this->assertTrue(session('user_is_locked'));
    }

    public function test_guest_is_redirected_from_lockscreen_to_login(): void
    {
        $this->get(route('lockscreen'))
            ->assertRedirect(route('login'));
    }
}
