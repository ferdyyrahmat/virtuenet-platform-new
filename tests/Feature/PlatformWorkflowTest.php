<?php

namespace Tests\Feature;

use App\Enums\ServiceRequestStatus;
use App\Jobs\ProvisionAiCredential;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlatformWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_request_runs_through_approval_and_queues_provisioning(): void
    {
        Queue::fake();
        $requester = User::factory()->create();
        $reviewer = $this->reviewer();

        $response = $this->actingAs($requester)->postJson(route('v1.requests.store'), [
            'type' => 'ai_token',
            'title' => 'Analyzer access',
            'description' => 'Token for the customer feedback analyzer.',
            'priority' => 'normal',
            'details' => [
                'purpose' => 'Analyze customer feedback without storing prompts.',
                'models' => ['gpt-5-mini'],
                'max_budget' => 50,
                'budget_duration' => 'monthly',
                'rpm_limit' => 30,
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $serviceRequest = ServiceRequest::firstOrFail();
        $this->assertSame(ServiceRequestStatus::Submitted, $serviceRequest->status);
        $this->assertCount(1, $serviceRequest->approvals);
        $this->assertDatabaseHas('system_notifications', ['user_id' => $reviewer->id]);

        $this->actingAs($reviewer)->postJson(route('admin.requests.review', $serviceRequest), [
            'action' => 'approve',
            'note' => 'Budget and model are appropriate.',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame(ServiceRequestStatus::Approved, $serviceRequest->fresh()->status);
        Queue::assertPushed(ProvisionAiCredential::class, fn ($job): bool => $job->request->is($serviceRequest));
    }

    public function test_revision_creates_a_new_complete_approval_round(): void
    {
        $requester = User::factory()->create();
        $reviewer = $this->reviewer();

        $this->actingAs($requester)->postJson(route('v1.requests.store'), [
            'type' => 'custom_system',
            'title' => 'Vendor portal',
            'description' => 'Portal for vendor onboarding and verification.',
            'priority' => 'high',
            'details' => [
                'problem' => 'Vendor onboarding is currently manual.',
                'target_users' => 'Procurement and external vendors.',
                'capabilities' => 'Registration, document review, and status tracking.',
                'needs_ai_analyzer' => false,
            ],
        ])->assertOk();

        $serviceRequest = ServiceRequest::firstOrFail();
        $this->actingAs($reviewer)->postJson(route('admin.requests.review', $serviceRequest), [
            'action' => 'revision',
            'note' => 'Clarify the document verification outcome.',
        ])->assertOk();

        $this->actingAs($requester)->putJson(route('v1.requests.resubmit', $serviceRequest), [
            'type' => 'custom_system',
            'title' => 'Vendor portal',
            'description' => 'Portal for vendor onboarding and verification.',
            'priority' => 'high',
            'details' => [
                'problem' => 'Vendor onboarding is currently manual.',
                'target_users' => 'Procurement and external vendors.',
                'capabilities' => 'Registration, document verification result, review, and status tracking.',
                'needs_ai_analyzer' => false,
            ],
        ])->assertOk();

        $this->assertDatabaseCount('service_request_approvals', 4);
        $this->assertDatabaseHas('service_request_approvals', ['service_request_id' => $serviceRequest->id, 'round' => 2, 'step' => 1, 'status' => 'pending']);
        $this->assertSame(ServiceRequestStatus::Submitted, $serviceRequest->fresh()->status);
    }

    private function reviewer(): User
    {
        $role = Role::create(['name' => 'Platform Operator', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('review service requests', 'web'));
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
