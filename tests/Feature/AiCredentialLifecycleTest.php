<?php

namespace Tests\Feature;

use App\Jobs\ProvisionAiCredential;
use App\Models\AiAccessCredential;
use App\Models\ExternalConnection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\AiCredentialService;
use App\Services\LiteLlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCredentialLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_credential_can_be_paused_resumed_and_revoked_through_litellm(): void
    {
        Http::fake([
            'https://gateway.test/key/*' => Http::response(['status' => 'ok']),
        ]);
        $credential = $this->credential();
        $service = app(AiCredentialService::class);

        $this->assertSame('paused', $service->changeStatus($credential, 'paused')->status);
        $this->assertSame('active', $service->changeStatus($credential, 'active')->status);
        $this->assertSame('revoked', $service->changeStatus($credential, 'revoked')->status);

        Http::assertSentCount(3);
    }

    public function test_admin_can_manage_virtual_key_through_authorized_gateway_route(): void
    {
        Http::fake([
            'https://gateway.test/key/block' => Http::response(['status' => 'ok']),
        ]);
        $credential = $this->credential();
        $role = Role::create(['name' => 'AI Gateway Operator', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('manage service requests', 'web'));
        $operator = User::factory()->create();
        $operator->assignRole($role);

        $this->actingAs($operator)
            ->patchJson(route('admin.ai-credentials.status', $credential), ['status' => 'paused'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'AI credential status synchronized.');

        $this->assertSame('paused', $credential->fresh()->status);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://gateway.test/key/block'
            && $request['keys'] === ['sk-old-key']
            && $request->hasHeader('Authorization', 'Bearer test-master'));
    }

    public function test_approved_ai_request_is_provisioned_end_to_end(): void
    {
        Http::fake([
            'https://gateway.test/key/generate' => Http::response(['key' => 'sk-generated-key', 'key_name' => 'key-id-1']),
        ]);
        ExternalConnection::create([
            'provider' => 'litellm', 'label' => 'LiteLLM', 'base_url' => 'https://gateway.test',
            'credentials' => ['master_key' => 'test-master'], 'enabled' => true,
        ]);
        $user = User::factory()->create();
        $request = ServiceRequest::create([
            'code' => 'REQ-AI-E2E', 'requester_id' => $user->id, 'type' => 'ai_token', 'status' => 'approved',
            'title' => 'AI', 'description' => 'AI', 'details' => ['models' => ['gpt-4o-mini'], 'max_budget' => 10],
        ]);

        (new ProvisionAiCredential($request))->handle(app(LiteLlmService::class));

        $credential = $request->fresh()->aiCredential;
        $this->assertSame('key-id-1', $credential->gateway_key_id);
        $this->assertSame('sk-generated-key', $credential->virtual_key);
        $this->assertSame('active', $credential->status);
        $this->assertTrue($credential->reveal_expires_at->isFuture());
    }

    public function test_key_can_only_be_revealed_once_by_its_owner(): void
    {
        $credential = $this->credential();
        $credential->update(['reveal_expires_at' => now()->addHour()]);
        $other = User::factory()->create();

        $this->actingAs($other)
            ->postJson(route('v1.ai-credentials.reveal', $credential))
            ->assertForbidden();

        $this->actingAs($credential->user)
            ->postJson(route('v1.ai-credentials.reveal', $credential))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['success' => true, 'key' => 'sk-old-key']);

        $this->actingAs($credential->user)
            ->postJson(route('v1.ai-credentials.reveal', $credential))
            ->assertUnprocessable();
    }

    private function credential(): AiAccessCredential
    {
        ExternalConnection::create([
            'provider' => 'litellm', 'label' => 'LiteLLM', 'base_url' => 'https://gateway.test',
            'credentials' => ['master_key' => 'test-master'], 'enabled' => true,
        ]);
        $user = User::factory()->create();
        $request = ServiceRequest::create([
            'code' => 'REQ-AI-LIFECYCLE', 'requester_id' => $user->id, 'type' => 'ai_token',
            'title' => 'AI', 'description' => 'AI', 'details' => [],
        ]);

        return AiAccessCredential::create([
            'service_request_id' => $request->id, 'user_id' => $user->id, 'external_user_id' => 'user-1',
            'key_alias' => 'key-1', 'virtual_key' => 'sk-old-key', 'key_hash' => hash('sha256', 'sk-old-key'),
            'key_preview' => 'sk-old...-key', 'status' => 'active',
        ]);
    }
}
