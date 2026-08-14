<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LarkSsoCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.lark.app_id' => 'test-app-id',
            'services.lark.app_secret' => 'test-app-secret',
            'services.lark.redirect_uri' => 'https://platform.test/auth/lark/callback',
            'services.lark.open_api_url' => 'https://open.larksuite.test',
            'services.lark.accounts_url' => 'https://accounts.larksuite.test',
            'services.lark.scope' => 'auth:user.id:read',
            'services.lark.allowed_domains' => 'example.test',
        ]);
    }

    private function fakeLarkProfile(array $data = []): void
    {
        Http::fake([
            'https://open.larksuite.test/open-apis/authen/v2/oauth/token' => Http::response([
                'code' => 0,
                'access_token' => 'access-token',
            ]),
            'https://open.larksuite.test/open-apis/authen/v1/user_info' => Http::response([
                'code' => 0,
                'data' => array_merge([
                    'open_id' => 'ou_lark_user',
                    'name' => 'Lark User',
                    'email' => 'lark@example.test',
                ], $data),
            ]),
        ]);
    }

    private function callbackUrl(string $state, string $code = 'auth-code'): string
    {
        return route('lark.callback', ['code' => $code, 'state' => $state]);
    }

    public function test_callback_creates_user_and_logs_in(): void
    {
        $this->fakeLarkProfile();
        Role::firstOrCreate(['name' => Role::USER, 'guard_name' => 'web']);

        $state = 'random-state-value';

        $this->withSession(['lark_oauth_state' => $state])
            ->get($this->callbackUrl($state))
            ->assertRedirect(route('v1.dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'lark@example.test',
            'lark_open_id' => 'ou_lark_user',
        ]);
        $this->assertAuthenticated();
    }

    public function test_callback_links_existing_user_by_email(): void
    {
        $this->fakeLarkProfile(['name' => 'Updated Name']);
        $existing = User::factory()->create(['email' => 'lark@example.test']);

        $state = 'random-state-value';

        $this->withSession(['lark_oauth_state' => $state])
            ->get($this->callbackUrl($state))
            ->assertRedirect(route('v1.dashboard'));

        $existing->refresh();

        $this->assertSame('ou_lark_user', $existing->lark_open_id);
        $this->assertSame('Updated Name', $existing->name);
    }

    public function test_callback_rejects_disallowed_email_domain(): void
    {
        $this->fakeLarkProfile(['email' => 'hacker@evil.com']);

        $state = 'random-state-value';

        $this->withSession(['lark_oauth_state' => $state])
            ->get($this->callbackUrl($state))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => 'hacker@evil.com']);
    }

    public function test_callback_rejects_state_mismatch(): void
    {
        $this->fakeLarkProfile();

        $this->withSession(['lark_oauth_state' => 'expected-state'])
            ->get($this->callbackUrl('wrong-state'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => 'lark@example.test']);
    }
}
