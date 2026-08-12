<?php

namespace Tests\Feature;

use App\Jobs\ProbeApplicationHealth;
use App\Jobs\SyncGithubTasks;
use App\Models\ExternalConnection;
use App\Models\GithubTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApplicationCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_postgres_application_catalog_without_blocking_on_health_probe(): void
    {
        Queue::fake();
        $user = $this->operator(['view delivery tasks', 'manage integrations']);

        $this->actingAs($user)->postJson(route('admin.applications.upsert'), [
            'repo_full_name' => 'rizub/example-app',
            'display_name' => 'Example App',
            'domain' => 'example.virtuenet.space',
            'environment' => 'virtuenet',
            'health_path' => '/health',
            'sync_enabled' => true,
            'backup_enabled' => true,
            'cost_center' => 'IT-001',
        ])->assertOk();

        $this->assertDatabaseHas('github_deployed_repos', ['repo_full_name' => 'rizub/example-app', 'cost_center' => 'IT-001']);
        Queue::assertPushed(ProbeApplicationHealth::class);
        $this->actingAs($user)->get(route('admin.applications.index'))->assertOk()->assertSee('Example App');
    }

    public function test_mirrored_mapping_is_rendered_with_task_owner_and_mandays(): void
    {
        config(['services.lark.task_url_template' => 'https://lark.example/tasks/{guid}']);
        Queue::fake();
        $user = $this->operator(['view delivery tasks']);
        GithubTask::create([
            'repository' => 'rizub/example-app', 'issue_number' => 42, 'title' => 'Production rollout', 'body' => 'Mandays: 2.5',
            'state' => 'open', 'assignee' => 'ferdyyrahmat', 'mandays' => 2.5, 'github_url' => 'https://github.com/rizub/example-app/issues/42',
        ]);
        DB::table('github_lark_task_mappings')->insert([
            'github_issue_url' => 'https://github.com/rizub/example-app/issues/42', 'lark_task_guid' => 'task-guid-42',
            'lark_user_id' => 'ou_42', 'repo_full_name' => 'rizub/example-app', 'github_issue_number' => 42, 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('admin.applications.index', ['tab' => 'tasks']))
            ->assertOk()->assertSee('Production rollout')->assertSee('ferdyyrahmat')->assertSee('2.5 md')
            ->assertSee('https://lark.example/tasks/task-guid-42', false)->assertSee('Synced');
    }

    public function test_sync_button_queues_the_portable_sync_service(): void
    {
        Queue::fake();
        $user = $this->operator(['sync delivery tasks']);
        ExternalConnection::create(['provider' => 'github_lark_sync', 'label' => 'GitHub–Lark Sync', 'base_url' => 'http://platform-github-lark-sync:3200', 'enabled' => true]);

        $this->actingAs($user)->postJson(route('admin.applications.sync'))->assertOk();

        Queue::assertPushed(SyncGithubTasks::class);
    }

    private function operator(array $permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }
}
