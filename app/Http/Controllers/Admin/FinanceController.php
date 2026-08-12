<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DeployedApplication;
use App\Models\FinanceBudget;
use App\Models\FinancialEntry;
use App\Models\PaymentInstrument;
use App\Models\StatementLine;
use App\Models\Subscription;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\LiteLlmService;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function index(Request $request, FinanceService $finance): View
    {
        $period = $request->date('period')?->startOfMonth() ?? now()->startOfMonth();
        $entriesQuery = FinancialEntry::query()
            ->with(['subscription', 'paymentInstrument', 'department', 'owner'])
            ->when($request->filled('period'), fn ($query) => $query->whereDate('accounting_period', $period))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('vendor'), fn ($query) => $query->where('vendor', $request->string('vendor')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('occurred_on');
        $currentEntries = FinancialEntry::whereDate('accounting_period', $period)->get();
        $subscriptions = Subscription::with('currentVersion')->where('status', 'active')->get();
        $monthlyCommitment = $subscriptions->reduce(fn (BigDecimal $sum, Subscription $subscription) => $sum->plus($finance->monthlyEquivalent($subscription)), BigDecimal::zero());
        $actual = $this->sum($currentEntries->whereIn('status', ['accrued', 'invoiced', 'paid']));
        $paid = $this->sum($currentEntries->where('status', 'paid'));
        $projected = $this->sum($currentEntries->where('status', 'projected'));
        $budget = FinanceBudget::whereDate('period', $period)->where('status', 'approved')->sum('amount_idr');

        return view('admin.finance.index', [
            'entries' => $entriesQuery->paginate(25)->withQueryString(),
            'subscriptions' => Subscription::with(['currentVersion', 'department'])->orderBy('vendor')->orderBy('product')->get(),
            'instruments' => PaymentInstrument::where('status', 'active')->orderBy('alias')->get(),
            'departments' => Department::where('active', true)->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'applications' => DeployedApplication::orderBy('display_name')->get(),
            'unresolvedLines' => StatementLine::with(['statementImport.paymentInstrument', 'financialEntry'])->whereNotIn('status', ['matched', 'refunded'])->latest()->paginate(20, ['*'], 'lines')->withQueryString(),
            'candidateEntries' => FinancialEntry::with('paymentInstrument')->whereIn('status', ['projected', 'accrued', 'invoiced', 'paid'])->latest()->limit(200)->get(),
            'budgets' => FinanceBudget::with('department')->whereDate('period', $period)->get(),
            'stats' => [
                'monthlyCommitment' => (string) $monthlyCommitment,
                'annualizedCommitment' => (string) $monthlyCommitment->multipliedBy(12),
                'actual' => $actual,
                'paid' => $paid,
                'projected' => $projected,
                'budget' => (string) $budget,
                'variance' => (string) BigDecimal::of((string) $budget)->minus($actual),
                'unmatched' => StatementLine::whereNotIn('status', ['matched', 'refunded'])->count(),
            ],
            'departmentRollup' => FinancialEntry::with('department')->whereDate('accounting_period', $period)->selectRaw('department_id, SUM(normalized_idr) AS total')->groupBy('department_id')->orderByDesc('total')->get(),
            'vendorRollup' => FinancialEntry::whereDate('accounting_period', $period)->selectRaw('vendor, SUM(normalized_idr) AS total')->groupBy('vendor')->orderByDesc('total')->limit(10)->get(),
            'applicationRollup' => FinancialEntry::whereDate('accounting_period', $period)->whereNotNull('application_repo_full_name')->selectRaw('application_repo_full_name, SUM(normalized_idr) AS total')->groupBy('application_repo_full_name')->orderByDesc('total')->get(),
            'monthlyRollup' => FinancialEntry::where('accounting_period', '>=', $period->copy()->subMonths(11))->selectRaw('accounting_period, SUM(normalized_idr) AS total')->groupBy('accounting_period')->orderBy('accounting_period')->get(),
            'period' => $period,
            'activeTab' => in_array($request->query('tab'), ['overview', 'ledger', 'reconciliation', 'budgets'], true) ? $request->query('tab') : 'overview',
        ]);
    }

    public function storeEntry(Request $request, FinanceService $finance): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in(['subscription', 'ai_usage', 'tax', 'fee', 'credit', 'refund', 'adjustment', 'one_off'])],
            'status' => ['required', Rule::in(['projected', 'accrued', 'invoiced', 'paid', 'refunded', 'void'])],
            'subscription_id' => ['nullable', 'exists:subscriptions,id'],
            'ai_credential_id' => ['nullable', 'exists:ai_access_credentials,id'],
            'application_repo_full_name' => ['nullable', 'exists:github_deployed_repos,repo_full_name'],
            'service_request_id' => ['nullable', 'exists:service_requests,id'],
            'payment_instrument_id' => ['nullable', 'exists:payment_instruments,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'cost_center' => ['nullable', 'string', 'max:100'],
            'vendor' => ['nullable', 'required_without:subscription_id', 'string', 'max:160'],
            'accounting_period' => ['required', 'date'],
            'occurred_on' => ['required', 'date'],
            'original_amount' => ['required', 'numeric'],
            'currency' => ['required', 'alpha', 'size:3'],
            'fx_rate' => ['required', 'numeric', 'gt:0'],
            'fx_source' => ['required', 'string', 'max:255'],
            'fx_effective_at' => ['nullable', 'date'],
            'evidence_reference' => ['nullable', 'url:http,https', 'max:2048'],
            'correction_of_id' => ['nullable', 'exists:financial_entries,id'],
            'notes' => ['nullable', 'required_if:kind,adjustment,one_off', 'string', 'max:5000'],
        ]);
        if (! collect(['subscription_id', 'ai_credential_id', 'application_repo_full_name', 'service_request_id'])->contains(fn ($field) => filled($validated[$field] ?? null)) && $validated['kind'] !== 'one_off') {
            throw ValidationException::withMessages(['subscription_id' => 'Link the entry to a subscription, AI key, application, request, or classify it as one-off.']);
        }
        $amount = BigDecimal::of((string) $validated['original_amount']);
        if (in_array($validated['kind'], ['credit', 'refund'], true) && $amount->isGreaterThan(0)) {
            throw ValidationException::withMessages(['original_amount' => 'Credits and refunds must use a negative amount.']);
        }
        if (! in_array($validated['kind'], ['credit', 'refund', 'adjustment'], true) && $amount->isLessThan(0)) {
            throw ValidationException::withMessages(['original_amount' => 'Use a positive amount for this entry type.']);
        }
        $finance->createEntry($validated, $request->user());

        return $this->success('Immutable ledger entry recorded.', route('admin.finance.index', ['tab' => 'ledger']));
    }

    public function importStatement(Request $request, FinanceService $finance): JsonResponse
    {
        $validated = $request->validate([
            'payment_instrument_id' => ['required', 'exists:payment_instruments,id'],
            'statement_period' => ['required', 'date_format:Y-m'],
            'statement' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
            'fx_rate' => ['required', 'numeric', 'gt:0'],
            'fx_source' => ['required', 'string', 'max:255'],
        ]);
        $finance->importStatement(
            $request->file('statement'),
            PaymentInstrument::findOrFail($validated['payment_instrument_id']),
            $validated['statement_period'],
            $validated['fx_rate'],
            $validated['fx_source'],
            $request->user()
        );

        return $this->success('Statement imported and exact matches reconciled.', route('admin.finance.index', ['tab' => 'reconciliation']));
    }

    public function reconcile(Request $request, StatementLine $line, FinanceService $finance): JsonResponse
    {
        $validated = $request->validate([
            'financial_entry_id' => ['nullable', 'exists:financial_entries,id'],
            'status' => ['required', Rule::in(['matched', 'partially_matched', 'duplicate', 'missing_invoice', 'unexpected', 'amount_variance', 'currency_variance', 'refunded', 'disputed'])],
            'review_note' => ['required', 'string', 'max:5000'],
        ]);
        $line->load('statementImport');
        $finance->reconcile($line, isset($validated['financial_entry_id']) ? FinancialEntry::find($validated['financial_entry_id']) : null, $validated['status'], $validated['review_note'], $request->user());

        return $this->success('Statement line review saved.', route('admin.finance.index', ['tab' => 'reconciliation']));
    }

    public function storeBudget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'period' => ['required', 'date_format:Y-m'],
            'amount_idr' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $period = Carbon::createFromFormat('Y-m', $validated['period'])->startOfMonth();
        if (FinanceBudget::where('department_id', $validated['department_id'])->whereDate('period', $period)->exists()) {
            throw ValidationException::withMessages(['period' => 'A budget already exists for this department and period.']);
        }
        FinanceBudget::create([...$validated, 'period' => $period, 'created_by' => $request->user()->id]);

        return $this->success('Budget submitted to a different checker.', route('admin.finance.index', ['tab' => 'budgets', 'period' => $period->format('Y-m')]));
    }

    public function reviewBudget(Request $request, FinanceBudget $budget): JsonResponse
    {
        $validated = $request->validate(['action' => ['required', Rule::in(['approve', 'reject'])]]);
        if ($budget->status !== 'pending_review') {
            throw ValidationException::withMessages(['budget' => 'This budget has already been reviewed.']);
        }
        if ($budget->created_by === $request->user()->id) {
            throw ValidationException::withMessages(['budget' => 'Maker and checker must be different users.']);
        }
        $budget->update([
            'status' => $validated['action'] === 'approve' ? 'approved' : 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        activity('finance')->causedBy($request->user())->performedOn($budget)->event('budget_reviewed')->log('Finance budget reviewed.');

        return $this->success('Budget review recorded.', route('admin.finance.index', ['tab' => 'budgets', 'period' => $budget->period->format('Y-m')]));
    }

    public function syncAi(Request $request, FinanceService $finance, LiteLlmService $liteLlm): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'between:1,90'],
            'fx_rate' => ['required', 'numeric', 'gt:0'],
            'fx_source' => ['required', 'string', 'max:255'],
        ]);
        $count = $finance->syncAiUsage($liteLlm, $validated['days'], $validated['fx_rate'], $validated['fx_source'], $request->user());

        return $this->success($count.' new AI spend entries imported.', route('admin.finance.index', ['tab' => 'ledger']));
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $period = Carbon::createFromFormat('Y-m', $request->string('period'))->startOfMonth();
        activity('finance')->causedBy($request->user())->event('report_exported')->withProperties(['period' => $period->format('Y-m')])->log('Finance reconciliation report exported.');

        return response()->streamDownload(function () use ($period): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['Reference', 'Date', 'Kind', 'State', 'Vendor', 'Department', 'Cost center', 'Entity', 'Original', 'Currency', 'FX rate', 'FX source', 'Normalized IDR', 'Evidence']);
            FinancialEntry::with(['department', 'subscription'])->whereDate('accounting_period', $period)->orderBy('occurred_on')->chunk(500, function ($entries) use ($stream): void {
                foreach ($entries as $entry) {
                    fputcsv($stream, [
                        $entry->reference, $entry->occurred_on->toDateString(), $entry->kind, $entry->status,
                        $entry->vendor, $entry->department?->name, $entry->cost_center,
                        $entry->subscription?->product ?? $entry->application_repo_full_name ?? ($entry->ai_credential_id ? 'AI key #'.$entry->ai_credential_id : 'One-off'),
                        $entry->original_amount, $entry->currency, $entry->fx_rate, $entry->fx_source,
                        $entry->normalized_idr, $entry->evidence_reference,
                    ]);
                }
            });
            fclose($stream);
        }, 'virtuenet-finance-'.$period->format('Y-m').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function sum($entries): string
    {
        return (string) $entries->reduce(fn (BigDecimal $sum, FinancialEntry $entry) => $sum->plus($entry->normalized_idr), BigDecimal::zero());
    }

    private function success(string $message, string $redirect): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'redirect' => $redirect]);
    }
}
