<?php

namespace Tests\Feature;

use App\Models\ExternalConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiGatewaySmokeCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_smoke_check_generates_and_revokes_canary_without_exposing_secret(): void
    {
        ExternalConnection::create([
            'provider' => 'litellm',
            'label' => 'LiteLLM',
            'base_url' => 'https://gateway.test',
            'credentials' => ['master_key' => 'test-master-key'],
            'enabled' => true,
        ]);

        Http::fake([
            'https://gateway.test/health/readiness' => Http::response(['status' => 'healthy']),
            'https://gateway.test/key/generate' => Http::response([
                'key' => 'sk-canary-super-secret',
                'key_name' => 'smoke-key-id',
            ]),
            'https://gateway.test/key/delete' => Http::response(['status' => 'ok']),
        ]);

        $exit = Artisan::call('platform:ai-gateway-smoke', ['--json' => true]);
        $output = trim(Artisan::output());
        $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exit);
        $this->assertTrue($result['passed']);
        $this->assertSame([
            'configured' => true,
            'health' => true,
            'key_generated' => true,
            'key_revoked' => true,
        ], $result['checks']);
        $this->assertStringNotContainsString('sk-canary-super-secret', $output);
        $this->assertStringNotContainsString('test-master-key', $output);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://gateway.test/key/generate'
            && $request['max_budget'] === 0.01
            && $request['budget_duration'] === '1d'
            && data_get($request->data(), 'metadata.purpose') === 'virtuenet-platform-issue-46-smoke');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://gateway.test/key/delete'
            && $request['keys'] === ['sk-canary-super-secret']);
    }

    public function test_smoke_check_fails_closed_when_canary_cleanup_fails(): void
    {
        ExternalConnection::create([
            'provider' => 'litellm',
            'label' => 'LiteLLM',
            'base_url' => 'https://gateway.test',
            'credentials' => ['master_key' => 'test-master-key'],
            'enabled' => true,
        ]);

        Http::fake([
            'https://gateway.test/health/readiness' => Http::response(['status' => 'healthy']),
            'https://gateway.test/key/generate' => Http::response(['key' => 'sk-canary-cleanup-test']),
            'https://gateway.test/key/delete' => Http::response(['message' => 'cleanup failed'], 500),
        ]);

        $exit = Artisan::call('platform:ai-gateway-smoke', ['--json' => true]);
        $output = trim(Artisan::output());
        $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exit);
        $this->assertFalse($result['passed']);
        $this->assertTrue($result['checks']['key_generated']);
        $this->assertFalse($result['checks']['key_revoked']);
        $this->assertStringContainsString('Canary key cleanup failed', $result['error']);
        $this->assertStringNotContainsString('sk-canary-cleanup-test', $output);
    }
}
