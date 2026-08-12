<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGithubTasks;
use App\Models\GithubTask;
use App\Services\GithubTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class GithubTaskController extends Controller
{
    public function index(): View
    {
        return view('admin.github-tasks.index', ['tasks' => GithubTask::latest('remote_updated_at')->paginate(30)]);
    }

    public function sync(GithubTaskService $github): JsonResponse
    {
        if (! $github->configured()) {
            return response()->json(['success' => false, 'message' => 'Configure and enable the GitHub connection first.', 'redirect' => null]);
        }

        SyncGithubTasks::dispatch();

        return response()->json(['success' => true, 'message' => 'GitHub to Lark sync queued.', 'redirect' => route('admin.github-tasks.index')]);
    }
}
