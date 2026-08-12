<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AiAccessCredential;
use App\Models\ExternalConnection;
use App\Models\FinancialEntry;
use App\Models\GithubTask;
use App\Models\ServiceRequest;
use App\Models\StatementLine;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Services\FinanceService;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(FinanceService $finance): View
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
        $departmentIds = $user->departments()->pluck('departments.id');
        $subscriptions = Subscription::query()
            ->with(['currentVersion', 'department', 'evidences'])
            ->unless($user->can('view subscriptions'), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('beneficiaries', fn (Builder $query) => $query->whereKey($user->id))
                ->orWhereIn('department_id', $departmentIds)))
            ->get();
        $monthlyCommitment = $subscriptions->where('status', 'active')->reduce(
            fn (BigDecimal $sum, Subscription $subscription) => $sum->plus($finance->monthlyEquivalent($subscription)),
            BigDecimal::zero()
        );
        $requestIndex = $user->can('view service requests') ? 'admin.requests.index' : 'v1.requests.index';
        $expiringAiCount = ($user->can('view ai usage') ? AiAccessCredential::query() : $user->aiCredentials())
            ->where('status', 'active')->whereBetween('expires_at', [now(), now()->addDays(30)])->count();
        $exceptions = collect([
            [
                'visible' => $user->can('manage service requests'),
                'label' => 'Provisioning failures',
                'count' => ServiceRequest::where('fulfilment_status', 'failed')->count(),
                'url' => route('admin.requests.index', ['fulfilment_status' => 'failed']),
                'tone' => 'danger',
            ],
            [
                'visible' => $user->can('manage service requests'),
                'label' => 'Approval sync drift',
                'count' => ServiceRequest::where('approval_source', 'lark')->where('approval_sync_status', '!=', 'synced')->count(),
                'url' => route('admin.requests.index', ['sync' => 'drift']),
                'tone' => 'warning',
            ],
            [
                'visible' => true,
                'label' => $user->can('view ai usage') ? 'AI keys expiring in 30 days' : 'My AI access expiring in 30 days',
                'count' => $expiringAiCount,
                'url' => $user->can('view ai usage') ? route('admin.ai-usage.index', ['attention' => 'expiring']) : route('v1.ai-usage.index', ['attention' => 'expiring']),
                'tone' => 'warning',
            ],
            [
                'visible' => $user->can('view subscriptions'),
                'label' => 'Missing subscription evidence',
                'count' => Subscription::whereIn('status', ['trial', 'active', 'renewal_review'])->whereDoesntHave('evidences')->count(),
                'url' => route('admin.subscriptions.index', ['evidence' => 'missing']),
                'tone' => 'warning',
            ],
            [
                'visible' => $user->can('view subscriptions'),
                'label' => 'Payment failures',
                'count' => Subscription::where('status', 'payment_failed')->count(),
                'url' => route('admin.subscriptions.index', ['status' => 'payment_failed']),
                'tone' => 'danger',
            ],
            [
                'visible' => $user->can('view finance'),
                'label' => 'Unmatched card charges',
                'count' => StatementLine::whereNotIn('status', ['matched', 'refunded'])->count(),
                'url' => route('admin.finance.index', ['tab' => 'reconciliation']),
                'tone' => 'danger',
            ],
            [
                'visible' => $user->can('view delivery tasks'),
                'label' => 'GitHub–Lark sync exceptions',
                'count' => GithubTask::where('sync_status', '!=', 'synced')->count(),
                'url' => route('admin.applications.index', ['tab' => 'tasks', 'sync_status' => 'failed']),
                'tone' => 'warning',
            ],
        ])->where('visible')->where('count', '>', 0)->values();

        $period = now()->startOfMonth();
        $actualSpend = $user->can('view finance')
            ? (string) FinancialEntry::whereDate('accounting_period', $period)->whereIn('status', ['accrued', 'invoiced', 'paid'])->sum('normalized_idr')
            : '0';

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
                'subscriptions' => $subscriptions->whereIn('status', ['trial', 'active', 'renewal_review'])->count(),
                'renewals_30' => $subscriptions->whereBetween('next_renewal_date', [today(), today()->addDays(30)])->count(),
                'renewals_60' => $subscriptions->whereBetween('next_renewal_date', [today()->addDays(31), today()->addDays(60)])->count(),
                'renewals_90' => $subscriptions->whereBetween('next_renewal_date', [today()->addDays(61), today()->addDays(90)])->count(),
            ],
            'requestIndex' => $requestIndex,
            'exceptions' => $exceptions,
            'subscriptions' => $subscriptions->sortBy('next_renewal_date')->take(5),
            'financeSnapshot' => [
                'monthlyCommitment' => (string) $monthlyCommitment,
                'annualizedCommitment' => (string) $monthlyCommitment->multipliedBy(12),
                'actualSpend' => $actualSpend,
            ],
            'recentRequests' => (clone $requests)->with(['requester', 'assignee'])->latest()->take(7)->get(),
            'connections' => $user->can('manage integrations') ? ExternalConnection::orderBy('provider')->get() : collect(),
            'aiGateway' => ExternalConnection::where('provider', 'litellm')->first(),
            'githubTasks' => $user->can('view delivery tasks') ? GithubTask::latest('remote_updated_at')->take(6)->get() : collect(),
            'openTickets' => Ticket::where('user_id', $user->id)->whereIn('status', ['open', 'in_progress', 'waiting_user'])->count(),
        ]);
    }
}
