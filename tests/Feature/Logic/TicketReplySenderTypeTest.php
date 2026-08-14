<?php

namespace Tests\Feature\Logic;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketReplySenderTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeTicket(?User $owner = null): Ticket
    {
        $owner = $owner ?? User::factory()->create();

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

    private function staffWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.tickets.reply', 'guard_name' => 'web']));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_reply_is_labeled_admin(): void
    {
        $admin = $this->staffWithRole(Role::ADMIN);
        $ticket = $this->makeTicket();

        $this->actingAs($admin)
            ->post(route('admin.tickets.reply', $ticket->id), ['message' => 'Admin response'])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'sender_name' => $admin->name,
        ]);
    }

    public function test_developer_reply_is_labeled_developer(): void
    {
        $developer = $this->staffWithRole(Role::DEVELOPER);
        $ticket = $this->makeTicket();

        $this->actingAs($developer)
            ->post(route('admin.tickets.reply', $ticket->id), ['message' => 'Developer response'])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'sender_type' => 'developer',
        ]);
    }

    public function test_regular_user_reply_is_labeled_user(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicket($user);

        $this->actingAs($user)
            ->post(route('v1.tickets.reply', $ticket->ticket_code), ['message' => 'User response'])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'sender_type' => 'user',
        ]);
    }
}