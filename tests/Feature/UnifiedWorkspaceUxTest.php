<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedWorkspaceUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_never_returns_another_requesters_record(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $visible = $this->request($owner, 'REQ-OWN-001', 'Private analyzer workspace');
        $this->request($other, 'REQ-OTHER-001', 'Private analyzer archive');

        $response = $this->actingAs($owner)->getJson(route('global.search', ['q' => 'Private analyzer']))->assertOk();

        $this->assertSame([$visible->code.' · '.$visible->title], collect($response->json('results'))->pluck('title')->all());
    }

    public function test_requester_dashboard_totals_link_to_scoped_filtered_records(): void
    {
        $user = User::factory()->create();
        $this->request($user, 'REQ-ACTIVE-001', 'Active request');

        $this->actingAs($user)->get(route('v1.dashboard'))
            ->assertOk()
            ->assertSee(route('v1.requests.index', ['view' => 'active']), false)
            ->assertSee('Action center')
            ->assertSee('My active subscriptions')
            ->assertDontSee('Finance Accountability');
    }

    public function test_request_detail_separates_lark_approval_from_platform_fulfilment(): void
    {
        $user = User::factory()->create();
        $serviceRequest = $this->request($user, 'REQ-TRACE-001', 'Traceable request', [
            'approval_source' => 'lark',
            'lark_instance_code' => 'LARK-INSTANCE-001',
            'lark_status' => 'approved',
            'approval_sync_status' => 'synced',
            'approval_synced_at' => now(),
            'approval_status' => 'approved',
            'fulfilment_status' => 'provisioning',
        ]);

        $this->actingAs($user)->get(route('v1.requests.show', $serviceRequest))
            ->assertOk()
            ->assertSee('End-to-end lifecycle')
            ->assertSee('Source · Lark')
            ->assertSee('LARK-INSTANCE-001')
            ->assertSee('Approval decision')
            ->assertSee('Platform fulfilment')
            ->assertSee('Provisioning');
    }

    private function request(User $user, string $code, string $title, array $overrides = []): ServiceRequest
    {
        return ServiceRequest::create([
            'code' => $code,
            'requester_id' => $user->id,
            'type' => 'ai_token',
            'title' => $title,
            'description' => 'A safely scoped request used for workspace validation.',
            'details' => ['purpose' => 'Testing'],
            'submitted_at' => now(),
            ...$overrides,
        ]);
    }
}
