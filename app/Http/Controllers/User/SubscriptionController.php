<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request, FinanceService $finance): View
    {
        $departmentIds = $request->user()->departments()->pluck('departments.id');
        $subscriptions = Subscription::query()
            ->with(['owner', 'renewalOwner', 'department', 'currentVersion', 'beneficiaries', 'evidences'])
            ->where(function ($query) use ($request, $departmentIds): void {
                $query->where('owner_id', $request->user()->id)
                    ->orWhereIn('department_id', $departmentIds)
                    ->orWhereHas('beneficiaries', fn ($query) => $query->whereKey($request->user()->id));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByRaw('next_renewal_date is null, next_renewal_date')
            ->get();
        $subscriptions->each(fn (Subscription $subscription) => $subscription->setAttribute('monthly_equivalent', $finance->monthlyEquivalent($subscription)));

        return view('subscriptions.index', [
            'subscriptions' => $subscriptions,
            'stats' => [
                'active' => $subscriptions->where('status', 'active')->count(),
                'renewal' => $subscriptions->where('next_renewal_date', '<=', today()->addDays(30))->count(),
                'attention' => $subscriptions->whereIn('status', ['renewal_review', 'payment_failed', 'suspended'])->count(),
            ],
        ]);
    }
}
