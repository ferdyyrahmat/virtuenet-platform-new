<?php

namespace Tests\Feature;

use App\Jobs\SyncGithubTasks;
use App\Models\ExternalConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GithubWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_github_delivery_is_idempotent(): void
    {
        Queue::fake();
        ExternalConnection::create([
            'provider' => 'github',
            'label' => 'GitHub',
            'credentials' => ['webhook_secret' => 'webhook-test-secret'],
            'settings' => ['repository' => 'virtuenet/github-lark-sync'],
            'enabled' => true,
        ]);
        $payload = json_encode(['action' => 'opened'], JSON_THROW_ON_ERROR);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-123',
            'HTTP_X_GITHUB_EVENT' => 'issues',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'webhook-test-secret'),
        ];

        $this->call('POST', route('webhooks.github'), [], [], [], $headers, $payload)->assertOk()->assertJsonPath('duplicate', false);
        $this->call('POST', route('webhooks.github'), [], [], [], $headers, $payload)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('integration_events', 1);
        Queue::assertPushed(SyncGithubTasks::class, 1);
    }
}
