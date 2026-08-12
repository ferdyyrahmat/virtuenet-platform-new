<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProbeApplicationHealth;
use App\Jobs\SyncCoolifyInventory;
use App\Jobs\SyncGithubTasks;
use App\Models\Department;
use App\Models\DeployedApplication;
use App\Models\GithubDeveloperMapping;
use App\Models\GithubLarkTaskMapping;
use App\Models\GithubTask;
use App\Models\ServiceHealthCheck;
use App\Models\ServiceUptimeIncident;
use App\Models\User;
use App\Models\VpsNode;
use App\Services\CoolifyService;
use App\Services\GithubLarkSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $applications = DeployedApplication::query()
            ->with(['department', 'node', 'owner'])
            ->when($request->filled('q'), fn ($query) => $query->where(function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where('repo_full_name', 'like', $term)->orWhere('display_name', 'like', $term)->orWhere('domain', 'like', $term);
            }))
            ->when($request->filled('health'), fn ($query) => $query->where('health_status', $request->string('health')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('vps_node_id'), fn ($query) => $query->where('vps_node_id', $request->integer('vps_node_id')))
            ->orderBy('display_name')
            ->paginate(12, ['*'], 'applications_page')
            ->withQueryString();

        $applications->each(function (DeployedApplication $application): void {
            if (! $application->last_checked_at || $application->last_checked_at->lt(now()->subMinutes(5))) {
                ProbeApplicationHealth::dispatch($application->repo_full_name)->afterResponse();
            }
        });

        $tasks = GithubTask::query()
            ->with(['mapping', 'department'])
            ->when($request->filled('task_q'), fn ($query) => $query->where(function ($query) use ($request) {
                $term = '%'.$request->string('task_q').'%';
                $query->where('title', 'like', $term)->orWhere('repository', 'like', $term)->orWhere('assignee', 'like', $term);
            }))
            ->when($request->filled('repository'), fn ($query) => $query->where('repository', $request->string('repository')))
            ->when($request->filled('task_department_id'), fn ($query) => $query->where('department_id', $request->integer('task_department_id')))
            ->when($request->string('sync_status')->toString() === 'synced', fn ($query) => $query->whereHas('mapping'))
            ->when($request->string('sync_status')->toString() === 'failed', fn ($query) => $query->where('sync_status', 'failed'))
            ->when($request->string('sync_status')->toString() === 'pending', fn ($query) => $query->whereDoesntHave('mapping')->where('sync_status', '!=', 'failed'))
            ->latest('remote_updated_at')
            ->paginate(20, ['*'], 'tasks_page')
            ->withQueryString();

        $uptime = ServiceHealthCheck::query()
            ->where('checked_at', '>=', now()->subDays(30))
            ->selectRaw("repo_full_name, AVG(CASE WHEN status = 'online' THEN 100 ELSE 0 END) AS percentage")
            ->groupBy('repo_full_name')
            ->pluck('percentage', 'repo_full_name');

        return view('admin.applications.index', [
            'applications' => $applications,
            'tasks' => $tasks,
            'mappings' => GithubLarkTaskMapping::query()->latest('updated_at')->paginate(30, ['*'], 'mappings_page')->withQueryString(),
            'developers' => GithubDeveloperMapping::query()->orderBy('github_username')->paginate(30, ['*'], 'developers_page')->withQueryString(),
            'departments' => Department::query()->where('active', true)->orderBy('name')->get(),
            'nodes' => VpsNode::query()->with(['department', 'applications'])->orderBy('name')->get(),
            'owners' => User::query()->orderBy('name')->get(['id', 'name']),
            'uptime' => $uptime,
            'incidents' => ServiceUptimeIncident::query()->with('application')->latest('started_at')->limit(30)->get(),
            'repositories' => DeployedApplication::query()->orderBy('repo_full_name')->pluck('repo_full_name'),
            'taskCounts' => GithubTask::query()->where('state', 'open')->selectRaw('repository, count(*) as total')->groupBy('repository')->pluck('total', 'repository'),
            'stats' => [
                'total' => DeployedApplication::count(),
                'online' => DeployedApplication::where('health_status', 'online')->count(),
                'open_tasks' => GithubTask::where('state', 'open')->count(),
                'stale' => DeployedApplication::whereNotNull('domain')->where(fn ($query) => $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinutes(10)))->count(),
                'governance_gaps' => DeployedApplication::where('environment', 'main')
                    ->where(fn ($query) => $query->whereNull('department_id')->orWhereNull('vps_node_id')->orWhereNull('owner_id')->orWhereNull('cost_center'))
                    ->count(),
                'open_incidents' => ServiceUptimeIncident::whereNull('ended_at')->count(),
                'mttr_seconds' => (int) ServiceUptimeIncident::whereNotNull('ended_at')->where('started_at', '>=', now()->subDays(30))->avg('duration_seconds'),
            ],
            'activeTab' => in_array($request->query('tab'), ['applications', 'infrastructure', 'uptime', 'tasks', 'developers', 'mappings'], true) ? $request->query('tab') : 'applications',
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'original_repo' => ['nullable', 'string', 'max:255'],
            'repo_full_name' => ['required', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'required_if:environment,main', 'regex:/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', 'max:255', Rule::unique('github_deployed_repos', 'domain')->ignore($request->string('original_repo')->toString(), 'repo_full_name')],
            'environment' => ['required', Rule::in(['virtuenet', 'main'])],
            'department_id' => ['nullable', 'required_if:environment,main', 'exists:departments,id'],
            'vps_node_id' => ['nullable', 'required_if:environment,main', 'exists:vps_nodes,id'],
            'owner_id' => ['nullable', 'required_if:environment,main', 'exists:users,id'],
            'cost_center' => ['nullable', 'required_if:environment,main', 'string', 'max:100'],
            'coolify_uuid' => ['nullable', 'required_if:environment,main', 'string', 'max:100', Rule::unique('github_deployed_repos', 'coolify_uuid')->ignore($request->string('original_repo')->toString(), 'repo_full_name')],
            'health_path' => ['required', 'regex:/^\/[A-Za-z0-9_\-\/.]*$/', 'max:255'],
            'thumbnail_url' => ['nullable', 'url:http,https', 'max:2048'],
            'domain_exception_reason' => ['nullable', 'string', 'max:1000'],
            'sync_enabled' => ['nullable', 'boolean'],
            'backup_enabled' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $department = filled($validated['department_id'] ?? null) ? Department::find($validated['department_id']) : null;
        if ($department && filled($validated['domain'] ?? null)) {
            $expectedSuffix = '.'.str($department->code ?: $department->name)->slug().'.virtuenet.space';
            if (! str_ends_with(strtolower($validated['domain']), strtolower($expectedSuffix)) && blank($validated['domain_exception_reason'] ?? null)) {
                throw ValidationException::withMessages(['domain_exception_reason' => "Use {app}{$expectedSuffix}, or explain this registered exception."]);
            }
        }

        DB::transaction(function () use ($request, $validated): void {
            if (filled($validated['original_repo'] ?? null) && $validated['original_repo'] !== $validated['repo_full_name']) {
                DeployedApplication::whereKey($validated['original_repo'])->delete();
            }
            DeployedApplication::updateOrCreate(['repo_full_name' => $validated['repo_full_name']], [
                ...collect($validated)->except(['original_repo', 'repo_full_name'])->all(),
                'sync_enabled' => $request->boolean('sync_enabled'),
                'backup_enabled' => $request->boolean('backup_enabled'),
                'health_status' => 'unknown',
                'last_checked_at' => null,
            ]);
        });

        activity('application')->causedBy($request->user())->event('upserted')->withProperties(['repository' => $validated['repo_full_name']])->log('Application catalog entry saved.');
        ProbeApplicationHealth::dispatch($validated['repo_full_name']);

        return response()->json(['success' => true, 'message' => 'Application saved.', 'redirect' => route('admin.applications.index')]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['repo_full_name' => ['required', Rule::exists('github_deployed_repos', 'repo_full_name')]]);
        DeployedApplication::whereKey($validated['repo_full_name'])->delete();
        activity('application')->causedBy($request->user())->event('deleted')->withProperties(['repository' => $validated['repo_full_name']])->log('Application catalog entry deleted.');

        return response()->json(['success' => true, 'message' => 'Application removed from the catalog.', 'redirect' => route('admin.applications.index')]);
    }

    public function sync(GithubLarkSyncService $sync): JsonResponse
    {
        if (! $sync->configured()) {
            return response()->json(['success' => false, 'message' => 'Configure and enable the GitHub–Lark Sync endpoint first.', 'redirect' => route('admin.connections.index')], 422);
        }
        SyncGithubTasks::dispatch();

        return response()->json(['success' => true, 'message' => 'GitHub–Lark synchronization queued.', 'redirect' => route('admin.applications.index', ['tab' => 'tasks'])]);
    }

    public function syncInventory(CoolifyService $coolify): JsonResponse
    {
        if (! $coolify->configured()) {
            return response()->json(['success' => false, 'message' => 'Configure and enable the Coolify endpoint first.', 'redirect' => route('admin.connections.index')], 422);
        }
        SyncCoolifyInventory::dispatch();

        return response()->json(['success' => true, 'message' => 'Coolify inventory synchronization queued.', 'redirect' => route('admin.applications.index')]);
    }

    public function upsertNode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'exists:vps_nodes,id'],
            'name' => ['required', 'string', 'max:100'],
            'cluster_key' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:100', Rule::unique('vps_nodes')->ignore($request->integer('id'))],
            'hostname' => ['required', 'string', 'max:255', Rule::unique('vps_nodes')->ignore($request->integer('id'))],
            'ip_address' => ['nullable', 'ip'],
            'account_type' => ['required', Rule::in(['it_shared', 'dept_own'])],
            'department_id' => ['nullable', 'exists:departments,id'],
            'coolify_server_uuid' => ['nullable', 'string', 'max:100', Rule::unique('vps_nodes')->ignore($request->integer('id'))],
            'cpu_cores' => ['nullable', 'integer', 'min:1', 'max:1024'],
            'ram_gb' => ['nullable', 'integer', 'min:1'],
            'disk_gb' => ['nullable', 'integer', 'min:1'],
            'cpu_usage_percent' => ['nullable', 'numeric', 'between:0,100'],
            'ram_usage_percent' => ['nullable', 'numeric', 'between:0,100'],
            'disk_usage_percent' => ['nullable', 'numeric', 'between:0,100'],
            'active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $node = VpsNode::updateOrCreate(['id' => $validated['id'] ?? null], [...collect($validated)->except('id')->all(), 'active' => $request->boolean('active')]);
        activity('infrastructure')->causedBy($request->user())->performedOn($node)->event('upserted')->log('VPS node saved.');

        return response()->json(['success' => true, 'message' => 'VPS node saved.', 'redirect' => route('admin.applications.index', ['tab' => 'infrastructure'])]);
    }

    public function destroyNode(Request $request, VpsNode $node): JsonResponse
    {
        if ($node->applications()->exists()) {
            return response()->json(['success' => false, 'message' => 'Move every application off this node before removing it.', 'redirect' => null], 422);
        }
        $node->delete();

        return response()->json(['success' => true, 'message' => 'VPS node removed.', 'redirect' => route('admin.applications.index', ['tab' => 'infrastructure'])]);
    }
}
