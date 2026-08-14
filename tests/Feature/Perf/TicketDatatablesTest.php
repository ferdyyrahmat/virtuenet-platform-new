<?php

namespace Tests\Feature\Perf;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketDatatablesTest extends TestCase
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

    private function makeTicket(User $owner, array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
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
        ], $overrides));
    }

    public function test_index_returns_server_side_datatable_json(): void
    {
        $admin = $this->staffWithPermission('admin.tickets.index');
        $owner = User::factory()->create();
        $this->makeTicket($owner, ['subject' => 'Pagination visible ticket']);

        $response = $this->actingAs($admin)
            ->get(route('admin.tickets.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->assertJsonFragment(['subject' => 'Pagination visible ticket']);
    }

    public function test_index_datatable_respects_status_filter(): void
    {
        $admin = $this->staffWithPermission('admin.tickets.index');
        $owner = User::factory()->create();

        $open = $this->makeTicket($owner, ['subject' => 'Open ticket']);
        $this->makeTicket($owner, ['subject' => 'Resolved ticket', 'status' => 'resolved']);

        $response = $this->actingAs($admin)
            ->get(route('admin.tickets.index', ['status' => 'open']), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();

        $this->assertSame(1, $response->json('recordsFiltered'));
        $this->assertStringContainsString('open', $response->json('data.0.status'));
        $this->assertSame($open->subject, $response->json('data.0.subject'));
    }

    public function test_index_renders_view_with_stats_for_normal_request(): void
    {
        $admin = $this->staffWithPermission('admin.tickets.index');
        $owner = User::factory()->create();
        $this->makeTicket($owner);

        $response = $this->actingAs($admin)->get(route('admin.tickets.index'));

        $response->assertOk()
            ->assertViewHas('stats');
    }
}