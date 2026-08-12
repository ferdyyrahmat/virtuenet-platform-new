<?php

namespace Tests\Feature;

use App\Models\ExternalConnection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LiteLlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiteLlmGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_is_rendered_directly_from_litellm_without_cloning_its_domain(): void
    {
        ExternalConnection::create([
            'provider' => 'litellm',
            'label' => 'LiteLLM',
            'base_url' => 'https://gateway.example.test',
            'credentials' => ['master_key' => 'sk-master-test'],
            'enabled' => true,
        ]);

        Http::fake([
            'gateway.example.test/user/daily/activity*' => Http::response(['metadata' => ['total_spend' => 12.34], 'results' => [['date' => '2026-08-11']]]),
            'gateway.example.test/spend/logs*' => Http::response(['data' => [[
                'model' => 'gpt-5-mini',
                'spend' => 0.12,
                'prompt_tokens' => 120,
                'completion_tokens' => 30,
                'status_code' => 200,
            ]]]),
        ]);

        $usage = app(LiteLlmService::class)->usage('virtuenet-user-1', '2026-08-01', '2026-08-11');

        $this->assertSame(12.34, $usage['summary']['total_spend']);
        $this->assertSame('gpt-5-mini', $usage['logs'][0]['model']);
        $this->assertSame(150, $usage['metrics']['total_tokens']);
        $this->assertSame(1, $usage['metrics']['requests']);
        $this->assertSame(100.0, $usage['metrics']['success_rate']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer sk-master-test'));
    }

    public function test_user_ai_monitoring_page_uses_the_existing_usage_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('v1.ai-usage.index'))
            ->assertOk()
            ->assertSee('My AI monitoring')
            ->assertSee('Gateway health')
            ->assertSee('Live monitoring')
            ->assertSee('Recent AI traffic')
            ->assertSee(route('v1.ai-usage.data'), false);
    }

    public function test_sidebar_shows_one_ai_monitoring_link_with_the_authorized_scope(): void
    {
        $personalUser = User::factory()->create();
        $personalSidebar = $this->actingAs($personalUser)->get(route('v1.dashboard'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($personalSidebar, '>AI Monitoring<'));
        $this->assertStringContainsString(route('v1.ai-usage.index'), $personalSidebar);

        $role = Role::create(['name' => 'AI Operator', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('view ai usage', 'web'));
        $operator = User::factory()->create();
        $operator->assignRole($role);
        $organizationSidebar = $this->actingAs($operator)->get(route('v1.dashboard'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($organizationSidebar, '>AI Monitoring<'));
        $this->assertStringContainsString(route('admin.ai-usage.index'), $organizationSidebar);
    }
}
