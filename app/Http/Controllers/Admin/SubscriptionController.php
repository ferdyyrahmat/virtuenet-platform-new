<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestType;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\PaymentInstrument;
use App\Models\ServiceRequest;
use App\Models\Subscription;
use App\Models\SubscriptionEvidence;
use App\Models\SubscriptionRenewalDecision;
use App\Models\SubscriptionVersion;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriptionController extends Controller
{
    public function index(Request $request, FinanceService $finance): View
    {
        $subscriptions = Subscription::query()
            ->with(['owner', 'renewalOwner', 'department', 'paymentInstrument', 'currentVersion', 'beneficiaries', 'request'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($query) => $query->where('vendor', 'like', $term)->orWhere('product', 'like', $term)->orWhere('plan', 'like', $term)->orWhere('cost_center', 'like', $term));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('renewal_window'), fn ($query) => $query->whereBetween('next_renewal_date', [today(), today()->addDays($request->integer('renewal_window'))]))
            ->orderByRaw('next_renewal_date is null, next_renewal_date')
            ->paginate(20)->withQueryString();
        $subscriptions->getCollection()->each(fn (Subscription $subscription) => $subscription->setAttribute('monthly_equivalent', $finance->monthlyEquivalent($subscription)));

        return view('admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'departments' => Department::where('active', true)->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'instruments' => PaymentInstrument::with(['custodian', 'department'])->orderBy('alias')->get(),
            'pendingVersions' => SubscriptionVersion::with(['subscription.owner', 'proposer'])->where('status', 'pending_review')->latest()->get(),
            'renewalDecisions' => SubscriptionRenewalDecision::with(['subscription.owner', 'maker', 'proposedVersion'])->latest()->limit(50)->get(),
            'approvedRequests' => ServiceRequest::with('requester')->where('type', ServiceRequestType::SaasSubscription)
                ->whereIn('status', [ServiceRequestStatus::Approved, ServiceRequestStatus::InProgress, ServiceRequestStatus::WaitingExternal, ServiceRequestStatus::Completed])
                ->whereDoesntHave('subscription')->latest()->get(),
            'upcoming' => Subscription::with(['owner', 'department', 'currentVersion'])->whereNotNull('next_renewal_date')->whereBetween('next_renewal_date', [today(), today()->addDays(90)])->orderBy('next_renewal_date')->get(),
            'stats' => [
                'active' => Subscription::where('status', 'active')->count(),
                'review' => Subscription::where('status', 'renewal_review')->count(),
                'due30' => Subscription::whereBetween('next_renewal_date', [today(), today()->addDays(30)])->count(),
                'paymentFailed' => Subscription::where('status', 'payment_failed')->count(),
            ],
            'activeTab' => in_array($request->query('tab'), ['registry', 'renewals', 'instruments'], true) ? $request->query('tab') : 'registry',
            'prefillRequest' => $request->integer('request') ?: null,
        ]);
    }

    public function store(Request $request, SubscriptionService $service): JsonResponse
    {
        $validated = $request->validate($this->subscriptionRules());
        $validated['auto_renew'] = $request->boolean('auto_renew');
        $validated['currency'] = strtoupper($validated['currency']);
        $validated['fx_rate'] = $validated['currency'] === 'IDR' ? '1' : $validated['fx_rate'];
        $validated['reminder_days'] = [30, 14, 7, 3, 1, 0];
        $validated['tags'] = collect(explode(',', (string) ($validated['tags'] ?? '')))->map(fn (string $tag) => trim($tag))->filter()->values()->all();
        $validated['beneficiary_notes'] = filled($validated['beneficiary_notes'] ?? null) ? [$validated['beneficiary_notes']] : null;
        $subscription = $service->create($validated, $request->user(), $request->file('evidence'));

        return $this->success('Subscription registered and sent to financial checker.', route('admin.subscriptions.index', ['highlight' => $subscription->id]));
    }

    public function approveVersion(Request $request, SubscriptionVersion $version, SubscriptionService $service): JsonResponse
    {
        $service->approveVersion($version, $request->user());

        return $this->success('Commercial version approved and ledger projection created.', route('admin.subscriptions.index'));
    }

    public function transition(Request $request, Subscription $subscription, SubscriptionService $service): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['trial', 'active', 'renewal_review', 'scheduled_to_cancel', 'cancelled', 'expired', 'suspended', 'payment_failed'])],
            'reason' => ['required', 'string', 'max:3000'],
            'evidence_reference' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
        $service->transition($subscription, $validated['status'], $request->user(), $validated['reason'], $validated['evidence_reference'] ?? null);

        return $this->success('Subscription lifecycle updated.', route('admin.subscriptions.index'));
    }

    public function proposeRenewal(Request $request, Subscription $subscription, SubscriptionService $service): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['renew_unchanged', 'renew_changed', 'cancel', 'defer', 'replace', 'request_clarification'])],
            'decision_due_at' => ['required', 'date'],
            'next_renewal_date' => ['nullable', 'required_if:decision,renew_unchanged,renew_changed', 'date', 'after:today'],
            'final_service_date' => ['nullable', 'required_if:decision,cancel,replace', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'url:http,https', 'max:2048'],
            'quantity' => ['nullable', 'required_if:decision,renew_changed', 'integer', 'min:1', 'max:100000'],
            'unit_price' => ['nullable', 'required_if:decision,renew_changed', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'required_if:decision,renew_changed', 'alpha', 'size:3'],
            'fx_rate' => ['nullable', 'required_if:decision,renew_changed', 'numeric', 'gt:0'],
            'fx_source' => ['nullable', 'required_if:decision,renew_changed', 'string', 'max:255'],
            'fx_effective_at' => ['nullable', 'required_if:decision,renew_changed', 'date'],
            'billing_cycle' => ['nullable', 'required_if:decision,renew_changed', Rule::in(['monthly', 'quarterly', 'annual', 'multi_year', 'usage_based', 'custom'])],
            'billing_interval_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'effective_from' => ['nullable', 'required_if:decision,renew_changed', 'date'],
            'version_reason' => ['nullable', 'required_if:decision,renew_changed', 'string', 'max:3000'],
            'evidence' => ['nullable', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,csv,xls,xlsx'],
        ]);
        if (($validated['currency'] ?? null) === 'IDR') {
            $validated['fx_rate'] = '1';
        }
        $service->proposeRenewal($subscription, $validated, $request->user(), $request->file('evidence'));

        return $this->success('Renewal decision submitted to a financial checker.', route('admin.subscriptions.index', ['tab' => 'renewals']));
    }

    public function reviewRenewal(Request $request, SubscriptionRenewalDecision $decision, SubscriptionService $service): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'required_if:action,reject', 'string', 'max:3000'],
        ]);
        $service->reviewRenewal($decision, $request->user(), $validated['action'] === 'approve', $validated['note'] ?? null);

        return $this->success('Renewal review recorded.', route('admin.subscriptions.index', ['tab' => 'renewals']));
    }

    public function storeInstrument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'exists:payment_instruments,id'],
            'alias' => ['required', 'string', 'max:160', Rule::unique('payment_instruments', 'alias')->ignore($request->integer('id'))],
            'issuer' => ['required', 'string', 'max:160'],
            'provider' => ['nullable', 'string', 'max:160'],
            'last_four' => ['required', 'digits:4'],
            'custodian_id' => ['required', 'exists:users,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'cost_center' => ['nullable', 'string', 'max:100'],
            'expiry_month' => ['required', 'integer', 'between:1,12'],
            'expiry_year' => ['required', 'integer', 'min:'.now()->year, 'max:'.(now()->year + 20)],
            'status' => ['required', Rule::in(['active', 'suspended', 'expired', 'closed'])],
        ]);
        PaymentInstrument::updateOrCreate(['id' => $validated['id'] ?? null], [
            ...collect($validated)->except('id')->all(),
            'created_by' => $request->integer('id') ? PaymentInstrument::find($request->integer('id'))?->created_by : $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success('Masked payment instrument saved.', route('admin.subscriptions.index', ['tab' => 'instruments']));
    }

    public function uploadEvidence(Request $request, Subscription $subscription, SubscriptionService $service): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['invoice', 'receipt', 'contract', 'renewal', 'cancellation', 'other'])],
            'evidence' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,csv,xls,xlsx'],
        ]);
        $service->storeEvidence($subscription, $request->file('evidence'), $request->user(), $validated['type']);

        return $this->success('Evidence stored with an integrity fingerprint.', route('admin.subscriptions.index', ['highlight' => $subscription->id]));
    }

    public function evidence(Request $request, SubscriptionEvidence $evidence): StreamedResponse
    {
        $subscription = $evidence->subscription()->with('beneficiaries')->firstOrFail();
        $departmentIds = $request->user()->departments()->pluck('departments.id');
        abort_unless(
            $request->user()->can('view subscriptions')
            || $subscription->owner_id === $request->user()->id
            || $subscription->beneficiaries->contains($request->user())
            || $departmentIds->contains($subscription->department_id),
            403
        );
        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404);

        return Storage::disk($evidence->disk)->download($evidence->path, $evidence->original_name);
    }

    private function subscriptionRules(): array
    {
        return [
            'service_request_id' => ['nullable', 'exists:service_requests,id', 'unique:subscriptions,service_request_id'],
            'vendor' => ['required', 'string', 'max:160'],
            'product' => ['required', 'string', 'max:160'],
            'plan' => ['nullable', 'string', 'max:160'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'business_purpose' => ['required', 'string', 'max:5000'],
            'account_identifier' => ['required', 'string', 'max:320'],
            'secret_reference' => ['nullable', 'string', 'max:500'],
            'owner_id' => ['required', 'exists:users,id'],
            'renewal_owner_id' => ['required', 'exists:users,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'payment_instrument_id' => ['nullable', 'exists:payment_instruments,id'],
            'cost_center' => ['required', 'string', 'max:100'],
            'project_reference' => ['nullable', 'string', 'max:160'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'annual', 'multi_year', 'usage_based', 'custom'])],
            'billing_interval_months' => ['nullable', 'required_if:billing_cycle,multi_year,custom', 'integer', 'min:1', 'max:120'],
            'start_date' => ['required', 'date'],
            'next_renewal_date' => ['required', 'date', 'after:start_date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'cancellation_deadline' => ['required', 'date', 'before_or_equal:next_renewal_date'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'auto_renew' => ['nullable', 'boolean'],
            'vendor_portal_url' => ['nullable', 'url:http,https', 'max:2048'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'beneficiary_notes' => ['nullable', 'string', 'max:3000'],
            'beneficiary_ids' => ['nullable', 'array'],
            'beneficiary_ids.*' => ['exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'alpha', 'size:3'],
            'fx_rate' => ['required', 'numeric', 'gt:0'],
            'fx_source' => ['required', 'string', 'max:255'],
            'fx_effective_at' => ['required', 'date'],
            'effective_from' => ['required', 'date'],
            'version_reason' => ['required', 'string', 'max:3000'],
            'version_evidence_reference' => ['nullable', 'url:http,https', 'max:2048'],
            'evidence' => ['nullable', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,csv,xls,xlsx'],
        ];
    }

    private function success(string $message, string $redirect): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'redirect' => $redirect]);
    }
}
