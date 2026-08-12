<?php

namespace App\Http\Controllers\System\Search;

use App\Http\Controllers\Controller;
use App\Models\DeployedApplication;
use App\Models\ServiceRequest;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());
        if (mb_strlen($term) < 2) {
            return response()->json(['success' => true, 'results' => []]);
        }

        $user = $request->user();
        $like = '%'.$term.'%';
        $departmentIds = $user->departments()->pluck('departments.id');
        $results = collect();

        $requests = ServiceRequest::query()
            ->with(['requester', 'department'])
            ->unless($user->can('view service requests'), fn (Builder $query) => $query->where('requester_id', $user->id))
            ->unless($user->isDeveloper() || ! $user->can('view service requests'), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('department_id')->orWhereIn('department_id', $departmentIds)
                ->orWhere('requester_id', $user->id)->orWhere('assigned_to', $user->id)))
            ->where(fn (Builder $query) => $query
                ->where('code', 'like', $like)->orWhere('title', 'like', $like)
                ->orWhere('lark_instance_code', 'like', $like)->orWhere('lark_approval_code', 'like', $like)
                ->orWhere('details', 'like', $like)
                ->orWhereHas('requester', fn (Builder $query) => $query->where('name', 'like', $like))
                ->orWhereHas('department', fn (Builder $query) => $query->where('name', 'like', $like)))
            ->latest()->limit(6)->get();

        foreach ($requests as $serviceRequest) {
            $results->push([
                'category' => 'Service requests',
                'title' => $serviceRequest->code.' · '.$serviceRequest->title,
                'subtitle' => $serviceRequest->type->label().' · '.$serviceRequest->approval_status->label().' / '.$serviceRequest->fulfilment_status->label(),
                'url' => $user->can('view service requests') ? route('admin.requests.show', $serviceRequest) : route('v1.requests.show', $serviceRequest),
                'icon' => 'mdi-clipboard-text-outline',
            ]);
        }

        $subscriptions = Subscription::query()
            ->with(['owner', 'department'])
            ->unless($user->can('view subscriptions'), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('beneficiaries', fn (Builder $query) => $query->whereKey($user->id))
                ->orWhereIn('department_id', $departmentIds)))
            ->where(fn (Builder $query) => $query
                ->where('vendor', 'like', $like)->orWhere('product', 'like', $like)
                ->orWhere('plan', 'like', $like)->orWhere('cost_center', 'like', $like)
                ->orWhere('account_fingerprint', hash('sha256', mb_strtolower($term)))
                ->orWhereHas('owner', fn (Builder $query) => $query->where('name', 'like', $like))
                ->orWhereHas('department', fn (Builder $query) => $query->where('name', 'like', $like)))
            ->limit(5)->get();

        foreach ($subscriptions as $subscription) {
            $results->push([
                'category' => 'Subscriptions',
                'title' => $subscription->vendor.' · '.$subscription->product,
                'subtitle' => ($subscription->plan ?: 'No plan').' · '.$subscription->masked_account.' · '.str($subscription->status)->replace('_', ' ')->title(),
                'url' => $user->can('view subscriptions') ? route('admin.subscriptions.index', ['q' => $subscription->vendor]) : route('v1.subscriptions.index'),
                'icon' => 'mdi-credit-card-refresh-outline',
            ]);
        }

        if ($user->can('view delivery tasks')) {
            DeployedApplication::query()->where(fn (Builder $query) => $query
                ->where('repo_full_name', 'like', $like)->orWhere('display_name', 'like', $like)
                ->orWhere('domain', 'like', $like)->orWhere('cost_center', 'like', $like))
                ->limit(5)->get()->each(fn (DeployedApplication $application) => $results->push([
                    'category' => 'Applications',
                    'title' => $application->display_name,
                    'subtitle' => $application->repo_full_name.' · '.str($application->health_status)->title(),
                    'url' => route('admin.applications.index', ['q' => $application->repo_full_name]),
                    'icon' => 'mdi-application-braces-outline',
                ]));
        }

        $tickets = Ticket::query()
            ->unless($user->can('view support tickets'), fn (Builder $query) => $query->where('user_id', $user->id))
            ->where(fn (Builder $query) => $query->where('ticket_code', 'like', $like)->orWhere('subject', 'like', $like))
            ->limit(4)->get();
        foreach ($tickets as $ticket) {
            $results->push([
                'category' => 'Support tickets',
                'title' => $ticket->ticket_code.' · '.$ticket->subject,
                'subtitle' => str($ticket->status)->replace('_', ' ')->title(),
                'url' => $user->can('view support tickets') ? route('admin.tickets.show', $ticket) : route('v1.tickets.show', $ticket->ticket_code),
                'icon' => 'mdi-lifebuoy',
            ]);
        }

        if ($user->can('view users')) {
            User::query()->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(4)->get()->each(fn (User $matchedUser) => $results->push([
                    'category' => 'People',
                    'title' => $matchedUser->name,
                    'subtitle' => $matchedUser->email,
                    'url' => route('admin.users.edit', $matchedUser),
                    'avatar' => $matchedUser->avatar_url,
                    'icon' => 'mdi-account-outline',
                ]));
        }

        return response()->json(['success' => true, 'results' => $results->take(20)->values()]);
    }
}
