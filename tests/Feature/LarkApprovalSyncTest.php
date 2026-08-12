<?php

namespace Tests\Feature;

use App\Jobs\CreateLarkApproval;
use App\Models\IntegrationEvent;
use App\Models\LarkApprovalContract;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\LarkService;
use App\Services\ServiceRequestWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LarkApprovalSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('services.lark.app_id', 'cli_test');
        config()->set('services.lark.app_secret', 'secret');
        config()->set('services.lark.approval_enabled', true);
        config()->set('services.lark.approval_code', 'E47A1D70-A980-4F01-AFAF-BFE2E4B3F69F');
        config()->set('services.lark.verification_token', 'verify-me');
        config()->set('services.lark.encrypt_key', null);
    }

    public function test_outbound_creation_uses_an_observed_contract_instead_of_legacy_widgets(): void
    {
        config()->set('services.lark.approval_outbound_enabled', true);
        config()->set('services.lark.approval_field_map', ['title' => 'title']);
        LarkApprovalContract::create([
            'approval_code' => config('services.lark.approval_code'),
            'contract_hash' => str_repeat('a', 64),
            'controls' => [['id' => 'widget-real-title', 'custom_id' => 'title', 'type' => 'input']],
            'nodes' => [],
            'active' => true,
            'observed_at' => now(),
        ]);
        $this->fakeLark(['instance_code' => 'instance-1']);
        $request = $this->request(['approval_source' => 'lark']);

        $job = new CreateLarkApproval($request);
        $job->handle(app(LarkService::class));
        $job->handle(app(LarkService::class));

        $this->assertSame('instance-1', $request->fresh()->lark_instance_code);
        Http::assertSent(fn ($sent): bool => str_ends_with($sent->url(), '/approval/v4/instances')
            && data_get(json_decode($sent['form'], true), '0.id') === 'widget-real-title');
        Http::assertSentCount(2);
    }

    public function test_platform_cannot_create_a_parallel_local_approval_when_lark_is_source_of_truth(): void
    {
        config()->set('services.lark.approval_outbound_enabled', false);

        $this->expectException(ValidationException::class);
        app(ServiceRequestWorkflow::class)->create(User::factory()->create(), [
            'type' => 'integration',
            'title' => 'ERP integration',
            'description' => 'Connect ERP to the warehouse.',
            'details' => [],
        ]);
    }

    public function test_inbound_lark_modified_instance_is_ingested_once_and_drives_state(): void
    {
        $user = User::factory()->create(['lark_open_id' => 'ou_requester']);
        $this->fakeLark($this->larkInstance('instance-2', 'APPROVED'));
        $payload = $this->event('event-1', 'instance-2');

        $this->postJson(route('webhooks.lark'), $payload)->assertOk()->assertJson(['duplicate' => false]);
        $this->postJson(route('webhooks.lark'), $payload)->assertOk()->assertJson(['duplicate' => true]);

        $request = ServiceRequest::where('lark_instance_code', 'instance-2')->firstOrFail();
        $this->assertSame($user->id, $request->requester_id);
        $this->assertSame('approved', $request->status->value);
        $this->assertSame('integration', $request->type->value);
        $this->assertSame(1, IntegrationEvent::where('provider', 'lark')->count());
    }

    public function test_other_definitions_are_quarantined_without_calling_lark(): void
    {
        Http::fake();
        $payload = $this->event('event-other', 'instance-other');
        data_set($payload, 'event.approval_code', 'OTHER-DEFINITION');

        $this->postJson(route('webhooks.lark'), $payload)->assertOk();

        $this->assertDatabaseHas('integration_events', ['external_id' => 'event-other', 'status' => 'quarantined']);
        $this->assertDatabaseCount('service_requests', 0);
        Http::assertNothingSent();
    }

    public function test_changed_lark_contract_is_quarantined(): void
    {
        User::factory()->create(['lark_open_id' => 'ou_requester']);
        $instances = [
            'instance-a' => $this->larkInstance('instance-a', 'PENDING'),
            'instance-b' => $this->larkInstance('instance-b', 'PENDING', [['id' => 'new-control', 'custom_id' => 'new_control', 'name' => 'New control', 'type' => 'input', 'value' => 'changed']]),
        ];
        $this->fakeLark(fn (string $code): array => $instances[$code]);

        $this->postJson(route('webhooks.lark'), $this->event('event-a', 'instance-a'))->assertOk();
        $this->postJson(route('webhooks.lark'), $this->event('event-b', 'instance-b'))->assertOk();

        $this->assertDatabaseHas('integration_events', ['external_id' => 'event-b', 'status' => 'quarantined']);
        $this->assertDatabaseMissing('service_requests', ['lark_instance_code' => 'instance-b']);
    }

    private function fakeLark(array|callable $instance): void
    {
        Http::fake(function ($request) use ($instance) {
            if (str_ends_with($request->url(), '/auth/v3/tenant_access_token/internal')) {
                return Http::response(['code' => 0, 'tenant_access_token' => 'tenant-token', 'expire' => 7200]);
            }
            if (preg_match('#/approval/v4/instances/([^/?]+)$#', $request->url(), $match)) {
                $data = is_callable($instance) ? $instance($match[1]) : $instance;

                return Http::response(['code' => 0, 'data' => $data]);
            }

            return Http::response(['code' => 0, 'data' => $instance]);
        });
    }

    private function event(string $eventId, string $instanceCode): array
    {
        return [
            'uuid' => $eventId,
            'token' => 'verify-me',
            'type' => 'event_callback',
            'event' => [
                'app_id' => 'cli_test',
                'type' => 'approval_instance',
                'approval_code' => config('services.lark.approval_code'),
                'instance_code' => $instanceCode,
                'status' => 'PENDING',
            ],
        ];
    }

    private function larkInstance(string $instanceCode, string $status, array $extraForm = []): array
    {
        $form = [
            ['id' => 'request-type', 'custom_id' => 'request_type', 'name' => 'Request type', 'type' => 'radioV2', 'value' => 'System integration'],
            ['id' => 'request-title', 'custom_id' => 'request_title', 'name' => 'Request title', 'type' => 'input', 'value' => 'ERP integration'],
            ...$extraForm,
        ];

        return [
            'approval_name' => 'Lark Modified',
            'approval_code' => config('services.lark.approval_code'),
            'instance_code' => $instanceCode,
            'serial_number' => substr(hash('sha1', $instanceCode), 0, 10),
            'open_id' => 'ou_requester',
            'status' => $status,
            'start_time' => (string) now()->subMinute()->getTimestampMs(),
            'end_time' => $status === 'PENDING' ? '0' : (string) now()->getTimestampMs(),
            'form' => json_encode($form, JSON_THROW_ON_ERROR),
            'task_list' => [['id' => 'task-'.$instanceCode, 'node_id' => 'node-manager', 'custom_node_id' => 'manager', 'node_name' => 'Manager', 'type' => 'OR', 'open_id' => 'ou_manager', 'status' => $status]],
        ];
    }

    private function request(array $overrides = []): ServiceRequest
    {
        $user = User::factory()->create(['lark_open_id' => 'ou_requester']);

        return ServiceRequest::create([
            'code' => 'REQ-LARK-'.str()->random(6), 'requester_id' => $user->id, 'type' => 'integration',
            'title' => 'Integration', 'description' => 'Integration request', 'details' => [],
            ...$overrides,
        ]);
    }
}
