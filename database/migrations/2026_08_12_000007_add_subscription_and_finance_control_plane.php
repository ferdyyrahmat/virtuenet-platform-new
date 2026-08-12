<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_instruments', function (Blueprint $table) {
            $table->id();
            $table->string('alias')->unique();
            $table->string('issuer');
            $table->string('provider')->nullable();
            $table->char('last_four', 4);
            $table->foreignId('custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_center')->nullable()->index();
            $table->unsignedTinyInteger('expiry_month')->nullable();
            $table->unsignedSmallInteger('expiry_year')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('vendor')->index();
            $table->string('product')->index();
            $table->string('plan')->nullable();
            $table->string('category')->index();
            $table->string('status')->default('draft')->index();
            $table->text('description')->nullable();
            $table->text('business_purpose');
            $table->text('account_identifier');
            $table->char('account_fingerprint', 64)->index();
            $table->char('identity_fingerprint', 64)->unique();
            $table->string('secret_reference')->nullable();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('renewal_owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_instrument_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_center')->index();
            $table->string('project_reference')->nullable()->index();
            $table->string('billing_cycle')->index();
            $table->unsignedSmallInteger('billing_interval_months')->nullable();
            $table->date('start_date');
            $table->date('next_renewal_date')->nullable()->index();
            $table->date('contract_end_date')->nullable();
            $table->date('cancellation_deadline')->nullable()->index();
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->boolean('auto_renew')->default(false)->index();
            $table->string('vendor_portal_url', 2048)->nullable();
            $table->json('beneficiary_notes')->nullable();
            $table->json('tags')->nullable();
            $table->json('reminder_days')->nullable();
            $table->string('source')->default('platform')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['department_id', 'status']);
            $table->index(['vendor', 'product', 'account_fingerprint']);
        });

        Schema::create('subscription_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('status')->default('pending_review')->index();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 19, 4);
            $table->decimal('subtotal', 19, 4);
            $table->decimal('discount', 19, 4)->default(0);
            $table->decimal('tax', 19, 4)->default(0);
            $table->decimal('fee', 19, 4)->default(0);
            $table->char('currency', 3);
            $table->decimal('normalized_idr', 19, 2);
            $table->decimal('fx_rate', 20, 8);
            $table->string('fx_source');
            $table->timestamp('fx_effective_at');
            $table->string('billing_cycle');
            $table->unsignedSmallInteger('billing_interval_months')->nullable();
            $table->date('effective_from');
            $table->text('reason');
            $table->string('evidence_reference', 2048)->nullable();
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['subscription_id', 'version']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('current_version_id')->nullable()->after('payment_instrument_id')->constrained('subscription_versions')->nullOnDelete();
        });

        Schema::create('subscription_user', function (Blueprint $table) {
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['subscription_id', 'user_id']);
        });

        Schema::create('subscription_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->string('evidence_reference', 2048)->nullable();
            $table->timestamp('occurred_at');
            $table->index(['subscription_id', 'occurred_at']);
        });

        Schema::create('subscription_renewal_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_version_id')->constrained('subscription_versions')->restrictOnDelete();
            $table->foreignId('proposed_version_id')->nullable()->constrained('subscription_versions')->nullOnDelete();
            $table->string('decision')->index();
            $table->string('status')->default('pending_review')->index();
            $table->date('renewal_date')->index();
            $table->date('next_renewal_date')->nullable();
            $table->date('final_service_date')->nullable();
            $table->timestamp('decision_due_at');
            $table->text('reason');
            $table->string('evidence_reference', 2048)->nullable();
            $table->foreignId('made_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'created_at']);
        });

        Schema::create('subscription_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('checkpoint');
            $table->date('renewal_date');
            $table->timestamp('due_at');
            $table->string('delivery_state')->default('pending')->index();
            $table->string('channel')->default('in_app');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['subscription_id', 'checkpoint', 'renewal_date'], 'subscription_reminder_unique');
        });

        Schema::create('subscription_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('renewal_decision_id')->nullable()->constrained('subscription_renewal_decisions')->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('disk')->default('local');
            $table->string('path', 2048);
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['subscription_id', 'sha256']);
        });

        Schema::create('financial_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('kind')->index();
            $table->string('status')->index();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_credential_id')->nullable()->constrained('ai_access_credentials')->nullOnDelete();
            $table->string('application_repo_full_name')->nullable();
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_instrument_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cost_center')->nullable()->index();
            $table->string('vendor')->index();
            $table->date('accounting_period')->index();
            $table->date('occurred_on')->index();
            $table->decimal('original_amount', 19, 4);
            $table->char('currency', 3);
            $table->decimal('normalized_idr', 19, 2);
            $table->decimal('fx_rate', 20, 8);
            $table->string('fx_source');
            $table->timestamp('fx_effective_at');
            $table->string('normalization_method');
            $table->string('evidence_reference', 2048)->nullable();
            $table->foreignId('correction_of_id')->nullable()->constrained('financial_entries')->nullOnDelete();
            $table->char('source_key', 64)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['department_id', 'accounting_period']);
            $table->index(['payment_instrument_id', 'accounting_period']);
        });

        Schema::create('statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_instrument_id')->constrained()->restrictOnDelete();
            $table->date('statement_period')->index();
            $table->string('disk')->default('local');
            $table->string('path', 2048);
            $table->string('original_name');
            $table->char('sha256', 64);
            $table->decimal('fx_rate', 20, 8);
            $table->string('fx_source');
            $table->timestamp('fx_effective_at');
            $table->unsignedInteger('row_count')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['payment_instrument_id', 'sha256']);
        });

        Schema::create('statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->date('occurred_on');
            $table->string('description');
            $table->string('external_reference')->nullable();
            $table->decimal('original_amount', 19, 4);
            $table->char('currency', 3);
            $table->decimal('normalized_idr', 19, 2);
            $table->decimal('fx_rate', 20, 8);
            $table->string('fx_source');
            $table->string('status')->default('unexpected')->index();
            $table->foreignId('financial_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['statement_import_id', 'row_number']);
        });

        Schema::create('finance_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->decimal('amount_idr', 19, 2);
            $table->string('status')->default('pending_review')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['department_id', 'period']);
        });

        Schema::table('subscription_purchase_request_details', function (Blueprint $table) {
            $table->string('vendor')->nullable()->after('service_request_id');
            $table->string('category')->nullable()->after('plan');
        });
        $this->normalizeSaasRequestData('annual');

        $permissions = [
            'view subscriptions' => 'View the corporate subscription registry and renewal calendar.',
            'manage subscriptions' => 'Create subscriptions, manage lifecycle, evidence, and renewal proposals.',
            'review subscription finances' => 'Approve subscription commercial versions and renewal decisions as checker.',
            'view finance' => 'View corporate-card ledger, reconciliation, budgets, and management rollups.',
            'manage finance' => 'Manage payment instruments, ledger entries, statement imports, and budgets.',
            'export finance reports' => 'Export audited finance reconciliation reports.',
        ];
        foreach ($permissions as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description, 'created_at' => now(), 'updated_at' => now()]
            );
        }
        $permissionIds = DB::table('permissions')->whereIn('name', array_keys($permissions))->pluck('id');
        $roleIds = DB::table('roles')->whereIn('name', ['Developer', 'Admin'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', [
            'view subscriptions', 'manage subscriptions', 'review subscription finances',
            'view finance', 'manage finance', 'export finance reports',
        ])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->normalizeSaasRequestData('yearly');

        Schema::table('subscription_purchase_request_details', function (Blueprint $table) {
            $table->dropColumn(['vendor', 'category']);
        });
        Schema::dropIfExists('finance_budgets');
        Schema::dropIfExists('statement_lines');
        Schema::dropIfExists('statement_imports');
        Schema::dropIfExists('financial_entries');
        Schema::dropIfExists('subscription_evidences');
        Schema::dropIfExists('subscription_reminders');
        Schema::dropIfExists('subscription_renewal_decisions');
        Schema::dropIfExists('subscription_lifecycle_events');
        Schema::dropIfExists('subscription_user');
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_version_id'));
        Schema::dropIfExists('subscription_versions');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('payment_instruments');
    }

    private function normalizeSaasRequestData(string $cycle): void
    {
        $from = $cycle === 'annual' ? 'yearly' : 'annual';
        DB::table('subscription_purchase_request_details')->where('billing_cycle', $from)->update(['billing_cycle' => $cycle]);
        DB::table('service_requests')->where('type', 'saas_subscription')->orderBy('id')->chunkById(200, function ($requests) use ($from, $cycle): void {
            foreach ($requests as $request) {
                $details = is_string($request->details) ? json_decode($request->details, true) : (array) $request->details;
                if (($details['billing_cycle'] ?? null) !== $from) {
                    continue;
                }
                $details['billing_cycle'] = $cycle;
                DB::table('service_requests')->where('id', $request->id)->update(['details' => json_encode($details, JSON_THROW_ON_ERROR)]);
            }
        });
        DB::table('request_templates')->where('request_type', 'saas_subscription')->orderBy('id')->get()->each(function ($template) use ($from, $cycle): void {
            $defaults = is_string($template->defaults) ? json_decode($template->defaults, true) : (array) $template->defaults;
            if (data_get($defaults, 'details.billing_cycle') === $from) {
                data_set($defaults, 'details.billing_cycle', $cycle);
                DB::table('request_templates')->where('id', $template->id)->update(['defaults' => json_encode($defaults, JSON_THROW_ON_ERROR)]);
            }
        });
    }
};
