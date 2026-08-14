<?php

namespace Tests\Feature\Tickets;

use App\Models\Developer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTicketActionsTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithPermission(string $permissionName): User
    {
        $role = Role::firstOrCreate(['name' => Role::ADMIN, 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeTicket(User $owner): Ticket
    {
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

    public function test_admin_can_assign_ticket_to_developer(): void
    {
        $admin = $this->staffWithPermission('admin.tickets.assign');
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $developer = Developer::create([
            'user_id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tickets.assign', $ticket->id), ['assigned_developer_id' => $developer->id])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assigned_developer_id' => $developer->id,
        ]);
    }

    public function test_admin_can_destroy_ticket(): void
    {
        $admin = $this->staffWithPermission('admin.tickets.destroy');
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($admin)
            ->delete(route('admin.tickets.destroy', $ticket->id))
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
    }

    public function test_user_without_permission_cannot_assign_ticket(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $ticket = $this->makeTicket($owner);

        $this->actingAs($user)
            ->post(route('admin.tickets.assign', $ticket->id), ['assigned_developer_id' => null])
            ->assertForbidden();
    }
}
