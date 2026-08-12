<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AiAccessCredential;
use App\Models\ExternalConnection;
use App\Models\GithubTask;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $requests = ServiceRequest::query();

        if (! $user->can('view service requests')) {
            $requests->where('requester_id', $user->id);
        }

        $activeStatuses = [
            ServiceRequestStatus::Submitted,
            ServiceRequestStatus::UnderReview,
            ServiceRequestStatus::Approved,
            ServiceRequestStatus::InProgress,
            ServiceRequestStatus::WaitingExternal,
        ];
        $aiCredentials = ($user->can('view ai usage') ? AiAccessCredential::query() : $user->aiCredentials())
            ->get(['status', 'models', 'max_budget', 'current_spend']);
        $aiBudget = (float) $aiCredentials->sum('max_budget');
        $aiSpend = (float) $aiCredentials->sum('current_spend');

        return view('dashboard.index', [
            'user' => $user,
            'stats' => [
                'active' => (clone $requests)->whereIn('status', $activeStatuses)->count(),
                'review' => (clone $requests)->whereIn('status', [ServiceRequestStatus::Submitted, ServiceRequestStatus::UnderReview])->count(),
                'revision' => (clone $requests)->where('status', ServiceRequestStatus::RevisionRequested)->count(),
                'completed' => (clone $requests)->where('status', ServiceRequestStatus::Completed)->count(),
                'unread' => $user->notifications()->where('is_read', false)->count(),
                'ai_spend' => $aiSpend,
                'ai_budget' => $aiBudget,
                'ai_budget_usage' => $aiBudget > 0 ? min(100, ($aiSpend / $aiBudget) * 100) : 0,
                'ai_tokens' => $aiCredentials->where('status', 'active')->count(),
                'ai_models' => $aiCredentials->pluck('models')->flatten()->filter()->unique()->count(),
            ],
            'recentRequests' => (clone $requests)->with(['requester', 'assignee'])->latest()->take(7)->get(),
            'connections' => $user->can('manage integrations') ? ExternalConnection::orderBy('provider')->get() : collect(),
            'aiGateway' => ExternalConnection::where('provider', 'litellm')->first(),
            'githubTasks' => $user->can('view delivery tasks') ? GithubTask::latest('remote_updated_at')->take(6)->get() : collect(),
            'openTickets' => Ticket::where('user_id', $user->id)->whereIn('status', ['open', 'in_progress', 'waiting_user'])->count(),
        ]);
    }
}
