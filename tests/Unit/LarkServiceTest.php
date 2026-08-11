<?php

namespace Tests\Unit;

use App\Services\LarkService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LarkServiceTest extends TestCase
{
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
        ]);
    }

    public function test_it_builds_an_oauth_url_and_retrieves_the_lark_profile(): void
    {
        Http::fake([
            'https://open.larksuite.test/open-apis/authen/v2/oauth/token' => Http::response([
                'code' => 0,
                'access_token' => 'access-token',
            ]),
            'https://open.larksuite.test/open-apis/authen/v1/user_info' => Http::response([
                'code' => 0,
                'data' => ['open_id' => 'ou_test', 'email' => 'user@example.test'],
            ]),
        ]);

        $service = app(LarkService::class);

        $this->assertStringContainsString('state=state-value', $service->authorizationUrl('state-value'));
        $this->assertSame('ou_test', $service->userFromCode('authorization-code')['open_id']);
    }
}
