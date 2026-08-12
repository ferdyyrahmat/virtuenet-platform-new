<?php

namespace Tests\Feature;

use App\Jobs\ProcessSubscriptionRenewals;
use App\Models\AiAccessCredential;
use App\Models\Department;
use App\Models\ExternalConnection;
use App\Models\FinanceBudget;
use App\Models\FinancialEntry;
use App\Models\PaymentInstrument;
use App\Models\Permission;
use App\Models\ServiceRequest;
use App\Models\StatementImport;
use App\Models\StatementLine;
use App\Models\Subscription;
use App\Models\SubscriptionReminder;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionFinanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private User $maker;

    private User $checker;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-12 08:00:00', 'Asia/Jakarta'));
        $this->department = Department::create(['code' => 'IT', 'name' => 'Information Technology', 'active' => true]);
        $this->maker = User::factory()->create(['name' => 'Finance Maker']);
        $this->checker = User::factory()->create(['name' => 'Finance Checker']);
        $this->maker->departments()->attach($this->department, ['is_primary' => true]);
        $this->checker->departments()->attach($this->department, ['is_primary' => true]);
        $this->grant($this->maker, ['view subscriptions', 'manage subscriptions', 'review subscription finances', 'view finance', 'manage finance', 'export finance reports']);
        $this->grant($this->checker, ['view subscriptions', 'review subscription finances', 'view finance']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_subscription_registry_masks_account_detects_duplicates_and_enforces_maker_checker(): void
    {
        $payload = $this->subscriptionPayload();
        $payload['tags'] = 'ai, productivity';
        $response = $this->actingAs($this->maker)->post(route('admin.subscriptions.store'), $payload);
        $response->assertOk()->assertJson(['success' => true]);
        $subscription = Subscription::with('versions')->firstOrFail();
        $this->assertNotSame('billing@example.com', $subscription->masked_account);
        $this->assertSame('bi***@example.com', $subscription->masked_account);
        $version = $subscription->versions->first();

        $this->actingAs($this->maker)->postJson(route('admin.subscriptions.versions.approve', $version))->assertUnprocessable();
        $this->actingAs($this->checker)->postJson(route('admin.subscriptions.versions.approve', $version))->assertOk();

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame($version->id, $subscription->current_version_id);
        $this->assertDatabaseHas('financial_entries', [
            'subscription_id' => $subscription->id,
            'kind' => 'subscription',
            'status' => 'projected',
            'original_amount' => '21.0000',
            'normalized_idr' => '336000.00',
        ]);
        $this->actingAs($this->maker)->get(route('v1.subscriptions.index'))->assertOk()->assertSee('bi***@example.com')->assertDontSee('billing@example.com');
        $this->actingAs($this->maker)->postJson(route('admin.subscriptions.store'), $payload)->assertUnprocessable();
    }

    public function test_changed_renewal_creates_a_new_immutable_commercial_version_and_history(): void
    {
        $subscription = $this->approvedSubscription();
        $this->actingAs($this->maker)->post(route('admin.subscriptions.renewals.store', $subscription), [
            'decision' => 'renew_changed',
            'decision_due_at' => now()->addDays(3)->toDateTimeString(),
            'next_renewal_date' => today()->addYear()->addMonth()->toDateString(),
            'reason' => 'Add one seat for the next annual period.',
            'quantity' => 3,
            'unit_price' => '11.00',
            'discount' => '0',
            'tax' => '3.30',
            'fee' => '0',
            'currency' => 'USD',
            'fx_rate' => '16100',
            'fx_source' => 'Bank Indonesia',
            'fx_effective_at' => now()->toDateTimeString(),
            'billing_cycle' => 'annual',
            'effective_from' => today()->addMonth()->toDateString(),
            'version_reason' => 'Seat increase approved by the owner.',
        ])->assertOk();
        $decision = $subscription->renewalDecisions()->with('proposedVersion')->firstOrFail();
        $this->assertSame('pending_review', $decision->proposedVersion->status);

        $this->actingAs($this->checker)->post(route('admin.subscriptions.renewals.review', $decision), ['action' => 'approve'])->assertOk();
        $subscription->refresh();
        $this->assertSame(2, $subscription->currentVersion->version);
        $this->assertSame('active', $subscription->status);
        $this->assertDatabaseHas('subscription_lifecycle_events', ['subscription_id' => $subscription->id, 'to_status' => 'active']);
        $this->assertDatabaseHas('subscription_reminders', ['subscription_id' => $subscription->id, 'checkpoint' => 'post_renewal_evidence', 'delivery_state' => 'pending']);
        $this->expectException(ValidationException::class);
        $subscription->currentVersion->update(['unit_price' => '1.00']);
    }

    public function test_renewal_scheduler_is_idempotent_and_escalates_auto_renew_governance(): void
    {
        $subscription = $this->approvedSubscription();
        $subscription->update([
            'auto_renew' => true,
            'next_renewal_date' => today()->addDay(),
            'cancellation_deadline' => today()->subDay(),
        ]);

        app(ProcessSubscriptionRenewals::class)->handle();
        $notificationCount = SystemNotification::count();
        app(ProcessSubscriptionRenewals::class)->handle();

        $this->assertSame('renewal_review', $subscription->refresh()->status);
        $this->assertSame(1, SubscriptionReminder::where('subscription_id', $subscription->id)->where('checkpoint', 'governance_overdue')->count());
        $this->assertSame($notificationCount, SystemNotification::count());
        $this->assertGreaterThan(0, $notificationCount);
    }

    public function test_statement_csv_reconciles_expected_charge_and_budget_requires_another_checker(): void
    {
        $instrument = $this->instrument();
        $subscription = $this->approvedSubscription($instrument);
        $csv = "date,description,amount,currency,reference\n".today()->toDateString().",OpenAI annual plan,21.00,USD,STMT-001\n";
        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $this->actingAs($this->maker)->withHeaders(['Accept' => 'application/json'])->post(route('admin.finance.statements.import'), [
            'payment_instrument_id' => $instrument->id,
            'statement_period' => today()->format('Y-m'),
            'fx_rate' => '16000',
            'fx_source' => 'Statement rate',
            'statement' => $file,
        ])->assertOk();

        $this->assertSame('matched', StatementLine::firstOrFail()->status);
        $this->assertSame('Statement rate', StatementLine::firstOrFail()->fx_source);
        $this->actingAs($this->maker)->withHeaders(['Accept' => 'application/json'])->post(route('admin.finance.statements.import'), [
            'payment_instrument_id' => $instrument->id,
            'statement_period' => today()->format('Y-m'),
            'fx_rate' => '16000',
            'fx_source' => 'Statement rate',
            'statement' => UploadedFile::fake()->createWithContent('statement-copy.csv', $csv),
        ])->assertUnprocessable();
        $this->assertSame(1, StatementImport::count());

        $this->actingAs($this->maker)->postJson(route('admin.finance.budgets.store'), [
            'department_id' => $this->department->id,
            'period' => today()->format('Y-m'),
            'amount_idr' => '1000000',
            'notes' => 'Monthly IT software budget.',
        ])->assertOk();
        $budget = FinanceBudget::firstOrFail();
        $this->actingAs($this->maker)->postJson(route('admin.finance.budgets.review', $budget), ['action' => 'approve'])->assertUnprocessable();
        $this->actingAs($this->checker)->postJson(route('admin.finance.budgets.review', $budget), ['action' => 'approve'])->assertOk();
        $this->assertSame('approved', $budget->refresh()->status);
        $this->actingAs($this->maker)->get(route('admin.finance.export', ['period' => today()->format('Y-m')]))->assertOk()->assertDownload();
        $this->assertSame($subscription->id, FinancialEntry::firstOrFail()->subscription_id);
    }

    public function test_litellm_spend_rolls_into_the_same_ledger_idempotently(): void
    {
        ExternalConnection::create([
            'provider' => 'litellm', 'label' => 'LiteLLM', 'base_url' => 'https://gateway.example.test',
            'credentials' => ['master_key' => 'sk-master-test'], 'enabled' => true,
        ]);
        $serviceRequest = ServiceRequest::create([
            'code' => 'REQ-AI-FINANCE', 'requester_id' => $this->maker->id, 'type' => 'ai_token',
            'title' => 'Finance AI key', 'description' => 'Ledger mapping', 'details' => [],
        ]);
        AiAccessCredential::create([
            'service_request_id' => $serviceRequest->id,
            'user_id' => $this->maker->id,
            'external_user_id' => 'finance-maker',
            'key_alias' => 'finance-ai-key',
            'virtual_key' => 'sk-finance-key',
            'key_hash' => hash('sha256', 'key'),
            'key_preview' => 'sk-fin...-key',
            'models' => ['gpt-5-mini'],
            'max_budget' => 10,
            'status' => 'active',
        ]);
        Http::fake([
            'gateway.example.test/user/daily/activity*' => Http::response(['metadata' => [], 'results' => []]),
            'gateway.example.test/spend/logs*' => Http::response(['data' => [[
                'request_id' => 'req-finance-1', 'key_alias' => 'finance-ai-key', 'model_group' => 'OpenAI',
                'spend' => '0.125', 'created_at' => now()->toIso8601String(), 'status_code' => 200,
            ]]]),
        ]);
        $payload = ['days' => 30, 'fx_rate' => '16000', 'fx_source' => 'Bank Indonesia'];
        $this->actingAs($this->maker)->post(route('admin.finance.sync-ai'), $payload)->assertOk()->assertJsonPath('message', '1 new AI spend entries imported.');
        $this->actingAs($this->maker)->post(route('admin.finance.sync-ai'), $payload)->assertOk()->assertJsonPath('message', '0 new AI spend entries imported.');

        $this->assertDatabaseHas('financial_entries', [
            'kind' => 'ai_usage', 'ai_credential_id' => AiAccessCredential::first()->id,
            'original_amount' => '0.1250', 'normalized_idr' => '2000.00', 'department_id' => $this->department->id,
        ]);
    }

    private function approvedSubscription(?PaymentInstrument $instrument = null): Subscription
    {
        $subscription = app(SubscriptionService::class)->create($this->subscriptionPayload($instrument), $this->maker);
        app(SubscriptionService::class)->approveVersion($subscription->versions()->firstOrFail(), $this->checker);

        return $subscription->refresh();
    }

    private function subscriptionPayload(?PaymentInstrument $instrument = null): array
    {
        return [
            'vendor' => 'OpenAI', 'product' => 'ChatGPT Plus', 'plan' => 'Plus', 'category' => 'AI productivity',
            'business_purpose' => 'Provide governed AI productivity access.', 'account_identifier' => 'billing@example.com',
            'owner_id' => $this->maker->id, 'renewal_owner_id' => $this->maker->id, 'department_id' => $this->department->id,
            'payment_instrument_id' => $instrument?->id, 'cost_center' => 'IT-001', 'billing_cycle' => 'annual',
            'start_date' => today()->toDateString(), 'next_renewal_date' => today()->addMonth()->toDateString(),
            'cancellation_deadline' => today()->addDays(20)->toDateString(), 'grace_days' => 3, 'auto_renew' => true,
            'quantity' => 2, 'unit_price' => '10.00', 'discount' => '1.00', 'tax' => '2.00', 'fee' => '0',
            'currency' => 'USD', 'fx_rate' => '16000', 'fx_source' => 'Bank Indonesia',
            'fx_effective_at' => now()->toDateTimeString(), 'effective_from' => today()->toDateString(),
            'version_reason' => 'Initial approved purchase terms', 'beneficiary_ids' => [$this->maker->id],
            'reminder_days' => [30, 14, 7, 3, 1, 0], 'tags' => ['ai'],
        ];
    }

    private function instrument(): PaymentInstrument
    {
        return PaymentInstrument::create([
            'alias' => 'Corporate Visa', 'issuer' => 'Bank Example', 'provider' => 'Visa', 'last_four' => '4242',
            'custodian_id' => $this->maker->id, 'department_id' => $this->department->id, 'cost_center' => 'IT-001',
            'expiry_month' => 12, 'expiry_year' => 2028, 'status' => 'active', 'created_by' => $this->maker->id,
        ]);
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
    }
}
