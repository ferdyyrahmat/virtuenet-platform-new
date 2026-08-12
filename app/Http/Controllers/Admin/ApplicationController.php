<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProbeApplicationHealth;
use App\Jobs\SyncGithubTasks;
use App\Models\Department;
use App\Models\DeployedApplication;
use App\Models\GithubDeveloperMapping;
use App\Models\GithubLarkTaskMapping;
use App\Models\GithubTask;
use App\Services\GithubLarkSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $applications = DeployedApplication::query()
            ->with('department')
            ->when($request->filled('q'), fn ($query) => $query->where(function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where('repo_full_name', 'like', $term)->orWhere('display_name', 'like', $term)->orWhere('domain', 'like', $term);
            }))
            ->when($request->filled('health'), fn ($query) => $query->where('health_status', $request->string('health')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
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

        return view('admin.applications.index', [
            'applications' => $applications,
            'tasks' => $tasks,
            'mappings' => GithubLarkTaskMapping::query()->latest('updated_at')->paginate(30, ['*'], 'mappings_page')->withQueryString(),
            'developers' => GithubDeveloperMapping::query()->orderBy('github_username')->paginate(30, ['*'], 'developers_page')->withQueryString(),
            'departments' => Department::query()->where('active', true)->orderBy('name')->get(),
            'repositories' => DeployedApplication::query()->orderBy('repo_full_name')->pluck('repo_full_name'),
            'taskCounts' => GithubTask::query()->where('state', 'open')->selectRaw('repository, count(*) as total')->groupBy('repository')->pluck('total', 'repository'),
            'stats' => [
                'total' => DeployedApplication::count(),
                'online' => DeployedApplication::where('health_status', 'online')->count(),
                'open_tasks' => GithubTask::where('state', 'open')->count(),
                'failed' => GithubTask::where('sync_status', 'failed')->count(),
            ],
            'activeTab' => in_array($request->query('tab'), ['applications', 'tasks', 'developers', 'mappings'], true) ? $request->query('tab') : 'applications',
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'original_repo' => ['nullable', 'string', 'max:255'],
            'repo_full_name' => ['required', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'regex:/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'cost_center' => ['nullable', 'string', 'max:100'],
            'sync_enabled' => ['nullable', 'boolean'],
            'backup_enabled' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

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
}
