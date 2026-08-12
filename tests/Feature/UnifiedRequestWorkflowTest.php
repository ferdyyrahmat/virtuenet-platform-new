<?php

namespace Tests\Feature;

use App\Enums\RequestApprovalStatus;
use App\Enums\RequestFulfilmentStatus;
use App\Models\Department;
use App\Models\RequestTemplate;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnifiedRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_department_request_is_idempotent_and_writes_typed_detail(): void
    {
        config()->set('services.lark.approval_enabled', false);
        $user = User::factory()->create();
        $primary = Department::create(['code' => 'IT', 'name' => 'Information Technology']);
        $finance = Department::create(['code' => 'FIN', 'name' => 'Finance']);
        $user->departments()->attach($primary, ['is_primary' => true]);
        $user->departments()->attach($finance, ['is_primary' => false]);
        $payload = $this->integrationPayload(['department_id' => $finance->id, 'idempotency_key' => 'request-123']);

        $first = $this->actingAs($user)->postJson(route('v1.requests.store'), $payload)->assertOk();
        $second = $this->actingAs($user)->postJson(route('v1.requests.store'), $payload)->assertOk();

        $this->assertSame($first->json('redirect'), $second->json('redirect'));
        $this->assertDatabaseCount('service_requests', 1);
        $request = ServiceRequest::firstOrFail();
        $this->assertSame($finance->id, $request->department_id);
        $this->assertSame(RequestApprovalStatus::Submitted, $request->approval_status);
        $this->assertSame(RequestFulfilmentStatus::NotStarted, $request->fulfilment_status);
        $this->assertDatabaseHas('integration_request_details', [
            'service_request_id' => $request->id,
            'source_system' => 'ERP',
            'target_system' => 'Warehouse',
        ]);
    }

    public function test_schema_and_template_are_versioned_and_type_safe(): void
    {
        config()->set('services.lark.approval_enabled', false);
        $user = User::factory()->create();
        $template = RequestTemplate::where('request_type', 'integration')->firstOrFail();

        $this->actingAs($user)->postJson(route('v1.requests.store'), $this->integrationPayload([
            'schema_version' => 2,
            'template_id' => $template->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('schema_version');

        $wrongTemplate = RequestTemplate::where('request_type', 'custom_system')->firstOrFail();
        $this->actingAs($user)->postJson(route('v1.requests.store'), $this->integrationPayload([
            'template_id' => $wrongTemplate->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('template_id');
    }

    public function test_private_attachment_can_only_be_downloaded_by_authorized_user(): void
    {
        config()->set('services.lark.approval_enabled', false);
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $response = $this->actingAs($owner)->post(route('v1.requests.store'), [
            ...$this->integrationPayload(),
            'attachments' => [UploadedFile::fake()->create('scope.pdf', 100, 'application/pdf')],
        ])->assertOk();

        $request = ServiceRequest::firstOrFail();
        $attachment = $request->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($other)->get(route('v1.request-attachments.download', $attachment))->assertForbidden();
        $this->actingAs($owner)->get(route('v1.request-attachments.download', $attachment))->assertOk();
        $this->assertStringContainsString((string) $request->id, $response->json('redirect'));
    }

    public function test_support_ticket_is_visible_as_a_unified_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('v1.tickets.store'), [
            'subject' => 'Checkout is failing',
            'application_reference' => 'VirtueNet Platform',
            'category' => 'bug',
            'severity' => 'high',
            'priority' => 'high',
            'description' => 'The checkout action returns an error.',
        ])->assertOk();

        $ticket = Ticket::firstOrFail();
        $request = $ticket->serviceRequest;
        $this->assertNotNull($request);
        $this->assertSame('VirtueNet Platform', $ticket->application_reference);
        $this->assertSame('high', $ticket->severity);
        $this->assertSame('support', $request->type->value);
        $this->assertSame(RequestApprovalStatus::Approved, $request->approval_status);
        $this->actingAs($user)->get(route('v1.requests.index'))->assertOk()->assertSee('Checkout is failing');
    }

    private function integrationPayload(array $overrides = []): array
    {
        return [
            'type' => 'integration',
            'schema_version' => 1,
            'title' => 'ERP warehouse integration',
            'description' => 'Synchronize stock and fulfillment updates.',
            'priority' => 'normal',
            'currency' => 'USD',
            'details' => [
                'source_system' => 'ERP',
                'target_system' => 'Warehouse',
                'scope' => 'Products, stock levels, and fulfillment status.',
                'access_status' => 'available',
            ],
            ...$overrides,
        ];
    }
}
