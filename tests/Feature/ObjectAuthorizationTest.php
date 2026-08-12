<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ObjectAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_open_or_reply_to_another_users_ticket(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ticket = Ticket::create([
            'ticket_code' => 'TCK-OBJECT-1', 'user_id' => $owner->id, 'name' => $owner->name,
            'email' => $owner->email, 'subject' => 'Private', 'description' => 'Private ticket',
        ]);

        $this->actingAs($other)->get(route('v1.tickets.show', $ticket->ticket_code))->assertForbidden();
        $this->actingAs($other)->post(route('v1.tickets.reply', $ticket->ticket_code), ['message' => 'Nope'])->assertForbidden();
    }

    public function test_reviewer_cannot_access_a_request_from_another_department(): void
    {
        $a = Department::create(['code' => 'A', 'name' => 'A']);
        $b = Department::create(['code' => 'B', 'name' => 'B']);
        $reviewer = User::factory()->create();
        $requester = User::factory()->create();
        $reviewer->departments()->attach($a, ['is_primary' => true]);
        $requester->departments()->attach($b, ['is_primary' => true]);
        $reviewer->givePermissionTo(Permission::findOrCreate('view service requests', 'web'));
        $request = ServiceRequest::create([
            'code' => 'REQ-OBJECT-1', 'requester_id' => $requester->id, 'department_id' => $b->id,
            'type' => 'integration', 'title' => 'Private', 'description' => 'Private request', 'details' => [],
        ]);

        $this->actingAs($reviewer)->get(route('admin.requests.show', $request))->assertForbidden();
    }
}
