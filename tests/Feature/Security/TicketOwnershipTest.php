<?php

namespace Tests\Feature\Security;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeTicket(?\App\Models\User $owner = null): Ticket
    {
        $owner ??= User::factory()->create();

        return Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'user_id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'phone' => null,
            'subject' => 'Test subject',
            'category' => 'bug',
            'priority' => 'medium',
            'status' => 'open',
            'description' => 'Test description',
        ]);
    }

    public function test_user_cannot_view_another_users_ticket(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($attacker)
            ->get(route('v1.tickets.show', $ticket->ticket_code))
            ->assertNotFound();
    }

    public function test_owner_can_view_own_ticket(): void
    {
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($owner)
            ->get(route('v1.tickets.show', $ticket->ticket_code))
            ->assertOk();
    }

    public function test_user_cannot_reply_to_another_users_ticket(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($attacker)
            ->post(route('v1.tickets.reply', $ticket->ticket_code), ['message' => 'unauthorized reply'])
            ->assertNotFound();

        $this->assertDatabaseMissing('ticket_replies', ['ticket_id' => $ticket->id]);
    }

    public function test_owner_can_reply_to_own_ticket(): void
    {
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($owner)
            ->post(route('v1.tickets.reply', $ticket->ticket_code), ['message' => 'my reply'])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'sender_type' => 'user',
        ]);
    }
}