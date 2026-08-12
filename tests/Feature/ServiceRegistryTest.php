<?php

namespace Tests\Feature;

use App\Jobs\ProbeApplicationHealth;
use App\Jobs\SyncCoolifyInventory;
use App\Models\Department;
use App\Models\DeployedApplication;
use App\Models\ExternalConnection;
use App\Models\User;
use App\Models\VpsNode;
use App\Services\CoolifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_only_sees_department_services_but_live_catalog_is_shared(): void
    {
        $it = Department::create(['code' => 'IT', 'name' => 'Information Technology']);
        $finance = Department::create(['code' => 'FIN', 'name' => 'Finance']);
        $user = User::factory()->create();
        $user->departments()->attach($it, ['is_primary' => true]);

        DeployedApplication::create(['repo_full_name' => 'rizub/it-app', 'display_name' => 'IT Workspace', 'domain' => 'workspace.it.virtuenet.space', 'department_id' => $it->id, 'health_status' => 'online']);
        DeployedApplication::create(['repo_full_name' => 'rizub/finance-app', 'display_name' => 'Finance Workspace', 'domain' => 'workspace.fin.virtuenet.space', 'department_id' => $finance->id, 'health_status' => 'online']);

        $this->actingAs($user)->get(route('v1.services.index'))
            ->assertOk()->assertSee('IT Workspace')->assertDontSee('Finance Workspace');
        $this->actingAs($user)->get(route('v1.services.index', ['scope' => 'catalog']))
            ->assertOk()->assertSee('IT Workspace')->assertSee('Finance Workspace');
    }

    public function test_production_application_requires_complete_governance_and_standard_domain(): void
    {
        $operator = $this->operator();
        $department = Department::create(['code' => 'IT', 'name' => 'Information Technology']);
        $owner = User::factory()->create();
        $this->actingAs($operator)->postJson(route('admin.applications.nodes.upsert'), [
            'name' => 'IT Primary', 'cluster_key' => 'it-primary', 'hostname' => 'it-primary.internal',
            'account_type' => 'it_shared', 'active' => true,
        ])->assertOk();
        $node = VpsNode::firstOrFail();
        $payload = [
            'repo_full_name' => 'rizub/production-app', 'display_name' => 'Production App', 'environment' => 'main',
            'domain' => 'production-app.it.virtuenet.space', 'health_path' => '/health', 'department_id' => $department->id,
            'vps_node_id' => $node->id, 'owner_id' => $owner->id, 'cost_center' => 'IT-001', 'coolify_uuid' => 'production-app-uuid',
        ];

        $this->actingAs($operator)->postJson(route('admin.applications.upsert'), $payload)->assertOk();
        $this->assertDatabaseHas('github_deployed_repos', ['repo_full_name' => 'rizub/production-app', 'environment' => 'main', 'vps_node_id' => $node->id]);

        $this->actingAs($operator)->postJson(route('admin.applications.upsert'), [...$payload, 'repo_full_name' => 'rizub/exception-app', 'coolify_uuid' => 'exception-app-uuid', 'domain' => 'legacy.example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('domain_exception_reason');
    }

    public function test_health_probe_records_incident_and_sends_one_in_app_alert_after_fifteen_minutes(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://example.com/health' => Http::failedConnection()]);
        $operator = $this->operator();
        $application = DeployedApplication::create([
            'repo_full_name' => 'rizub/offline-app', 'display_name' => 'Offline App', 'domain' => 'example.com',
            'owner_id' => $operator->id, 'offline_since' => now()->subMinutes(16),
        ]);

        (new ProbeApplicationHealth($application->repo_full_name))->handle();
        (new ProbeApplicationHealth($application->repo_full_name))->handle();

        $this->assertDatabaseHas('service_health_checks', ['repo_full_name' => $application->repo_full_name, 'status' => 'offline']);
        $this->assertDatabaseHas('service_uptime_incidents', ['repo_full_name' => $application->repo_full_name]);
        $this->assertDatabaseCount('system_notifications', 1);
    }

    public function test_coolify_inventory_maps_repository_runtime_domain_branch_and_node(): void
    {
        Http::preventStrayRequests();
        Http::fake(['coolify.example/api/v1/applications' => Http::response([[
            'uuid' => 'app-uuid', 'name' => 'Synced App', 'git_repository' => 'https://github.com/rizub/synced-app.git',
            'git_branch' => 'main', 'fqdn' => 'https://synced-app.it.virtuenet.space', 'status' => 'running',
            'destination' => ['server_uuid' => 'server-uuid'],
        ]])]);
        ExternalConnection::create([
            'provider' => 'coolify', 'label' => 'Coolify', 'base_url' => 'https://coolify.example',
            'credentials' => ['token' => 'secret'], 'enabled' => true,
        ]);
        $node = VpsNode::create(['name' => 'IT Primary', 'cluster_key' => 'it-primary', 'hostname' => 'it-primary.internal', 'coolify_server_uuid' => 'server-uuid']);

        (new SyncCoolifyInventory)->handle(app(CoolifyService::class));

        $this->assertDatabaseHas('github_deployed_repos', [
            'repo_full_name' => 'rizub/synced-app', 'coolify_uuid' => 'app-uuid', 'environment' => 'main',
            'domain' => 'synced-app.it.virtuenet.space', 'runtime_status' => 'running', 'vps_node_id' => $node->id,
        ]);
    }

    private function operator(): User
    {
        $permission = Permission::findOrCreate('manage integrations', 'web');
        $role = Role::findOrCreate('Infrastructure Operator', 'web');
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
