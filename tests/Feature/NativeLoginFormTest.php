<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NativeLoginFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_login_returns_the_ajax_contract(): void
    {
        $user = User::factory()->create([
            'email' => 'developer@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('auth-json-form');

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withHeader('Accept', 'application/json')
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirect', '/v1/dashboard')
            ->assertJsonStructure(['message']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_failed_login_returns_the_ajax_error_contract(): void
    {
        User::factory()->create([
            'email' => 'developer@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withHeader('Accept', 'application/json')
            ->post(route('login.store'), [
                'email' => 'developer@example.com',
                'password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('redirect', null)
            ->assertJsonStructure(['message', 'errors' => ['email']]);
    }
}
