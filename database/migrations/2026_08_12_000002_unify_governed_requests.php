<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_type_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedSmallInteger('version');
            $table->string('label');
            $table->text('description');
            $table->string('icon');
            $table->json('validation_schema');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->unique(['type', 'version']);
        });

        Schema::create('request_templates', function (Blueprint $table) {
            $table->id();
            $table->string('request_type')->index();
            $table->unsignedSmallInteger('version');
            $table->string('name');
            $table->text('summary');
            $table->json('defaults');
            $table->boolean('active')->default(true)->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['request_type', 'name', 'version']);
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('approval_status')->default('submitted')->index()->after('status');
            $table->string('fulfilment_status')->default('not_started')->index()->after('approval_status');
            $table->unsignedSmallInteger('schema_version')->default(1)->after('type');
            $table->foreignId('template_id')->nullable()->after('parent_id')->constrained('request_templates')->nullOnDelete();
            $table->string('idempotency_key', 128)->nullable()->after('source_record_id');
            $table->unique(['requester_id', 'idempotency_key']);
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->longText('lark_form_snapshot')->nullable()->change();
        });
        DB::table('service_requests')->whereNotNull('lark_form_snapshot')->orderBy('id')->chunkById(200, function ($requests): void {
            foreach ($requests as $request) {
                $snapshot = is_string($request->lark_form_snapshot)
                    ? json_decode($request->lark_form_snapshot, true)
                    : (array) $request->lark_form_snapshot;
                DB::table('service_requests')->where('id', $request->id)->update([
                    'lark_form_snapshot' => Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR)),
                ]);
            }
        });

        $this->createDetailTables();

        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('local');
            $table->string('path', 2048);
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index(['service_request_id', 'created_at']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('service_request_id')->nullable()->unique()->after('id')->constrained('service_requests')->nullOnDelete();
            $table->string('application_reference')->nullable()->index();
            $table->string('severity')->nullable()->index();
            $table->string('github_issue_url', 2048)->nullable();
        });

        $this->seedDefinitionsAndTemplates();
        $this->backfillRequests();
        $this->backfillTickets();
    }

    public function down(): void
    {
        DB::table('service_requests')->where('source', 'legacy_ticket')->delete();
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_request_id');
            $table->dropColumn(['application_reference', 'severity', 'github_issue_url']);
        });
        Schema::dropIfExists('request_attachments');
        Schema::dropIfExists('subscription_purchase_request_details');
        Schema::dropIfExists('integration_request_details');
        Schema::dropIfExists('system_development_request_details');
        Schema::dropIfExists('ai_access_request_details');
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropUnique(['requester_id', 'idempotency_key']);
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn(['approval_status', 'fulfilment_status', 'schema_version', 'idempotency_key']);
        });
        DB::table('service_requests')->whereNotNull('lark_form_snapshot')->orderBy('id')->chunkById(200, function ($requests): void {
            foreach ($requests as $request) {
                DB::table('service_requests')->where('id', $request->id)->update([
                    'lark_form_snapshot' => Crypt::decryptString($request->lark_form_snapshot),
                ]);
            }
        });
        Schema::table('service_requests', function (Blueprint $table) {
            $table->json('lark_form_snapshot')->nullable()->change();
        });
        Schema::dropIfExists('request_templates');
        Schema::dropIfExists('request_type_definitions');
    }

    private function createDetailTables(): void
    {
        Schema::create('ai_access_request_details', function (Blueprint $table) {
            $table->foreignId('service_request_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('purpose')->nullable();
            $table->json('models');
            $table->decimal('max_budget', 15, 2)->nullable();
            $table->string('budget_duration')->nullable();
            $table->unsignedInteger('rpm_limit')->nullable();
            $table->unsignedBigInteger('tpm_limit')->nullable();
            $table->timestamps();
        });
        Schema::create('system_development_request_details', function (Blueprint $table) {
            $table->foreignId('service_request_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('problem')->nullable();
            $table->text('target_users')->nullable();
            $table->text('capabilities')->nullable();
            $table->boolean('needs_ai_analyzer')->default(false);
            $table->json('ai_models');
            $table->decimal('ai_max_budget', 15, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('integration_request_details', function (Blueprint $table) {
            $table->foreignId('service_request_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('source_system')->nullable();
            $table->string('target_system')->nullable();
            $table->text('scope')->nullable();
            $table->string('access_status')->nullable();
            $table->timestamps();
        });
        Schema::create('subscription_purchase_request_details', function (Blueprint $table) {
            $table->foreignId('service_request_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('product')->nullable();
            $table->string('plan')->nullable();
            $table->unsignedInteger('seats')->nullable();
            $table->string('billing_cycle')->nullable();
            $table->string('vendor_url', 2048)->nullable();
            $table->text('business_reason')->nullable();
            $table->timestamps();
        });
    }

    private function seedDefinitionsAndTemplates(): void
    {
        $now = now();
        $definitions = [
            ['ai_token', 'AI Token', 'Request scoped AI Gateway access and budget.', 'mdi-key-variant', ['purpose', 'models', 'max_budget', 'budget_duration']],
            ['custom_system', 'Custom System', 'Request a custom application with optional AI Analyzer.', 'mdi-application-braces-outline', ['problem', 'target_users', 'capabilities']],
            ['integration', 'System Integration', 'Request work connecting systems, data, and workflows.', 'mdi-connection', ['source_system', 'target_system', 'scope', 'access_status']],
            ['saas_subscription', 'SaaS Subscription', 'Request a governed software subscription purchase.', 'mdi-credit-card-outline', ['product', 'plan', 'seats', 'billing_cycle', 'business_reason']],
            ['support', 'Support', 'Report a bug or request an application improvement.', 'mdi-lifebuoy', ['application', 'severity', 'category', 'description']],
        ];
        foreach ($definitions as [$type, $label, $description, $icon, $required]) {
            DB::table('request_type_definitions')->insert([
                'type' => $type, 'version' => 1, 'label' => $label, 'description' => $description, 'icon' => $icon,
                'validation_schema' => json_encode(['version' => 1, 'required' => $required], JSON_THROW_ON_ERROR),
                'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $templates = [
            ['custom_system', 'AI Feature', 'Add an AI-assisted workflow to an internal system.', ['title' => 'AI-assisted workflow', 'details' => ['needs_ai_analyzer' => true]]],
            ['integration', 'Middleware', 'Build middleware between internal or partner systems.', ['title' => 'Middleware integration', 'details' => ['access_status' => 'unknown']]],
            ['integration', 'System Integration', 'Connect two systems and automate data movement.', ['title' => 'System integration', 'details' => ['access_status' => 'unknown']]],
            ['custom_system', 'Fullstack Application', 'Build a complete internal web application.', ['title' => 'Internal application', 'details' => ['needs_ai_analyzer' => false]]],
            ['ai_token', 'Standalone AI Access', 'Provision a dedicated virtual AI key.', ['title' => 'AI access', 'details' => ['budget_duration' => 'monthly']]],
            ['saas_subscription', 'New SaaS Purchase', 'Purchase a new governed SaaS subscription.', ['title' => 'SaaS purchase', 'details' => ['billing_cycle' => 'yearly', 'seats' => 1]]],
        ];
        foreach ($templates as [$type, $name, $summary, $defaults]) {
            DB::table('request_templates')->insert([
                'request_type' => $type, 'version' => 1, 'name' => $name, 'summary' => $summary,
                'defaults' => json_encode($defaults, JSON_THROW_ON_ERROR), 'active' => true,
                'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function backfillRequests(): void
    {
        DB::table('service_requests')->orderBy('id')->chunkById(200, function ($requests): void {
            foreach ($requests as $request) {
                $details = is_string($request->details) ? json_decode($request->details, true) ?: [] : (array) $request->details;
                [$approval, $fulfilment] = $this->states($request->status);
                DB::table('service_requests')->where('id', $request->id)->update([
                    'approval_status' => $approval, 'fulfilment_status' => $fulfilment, 'schema_version' => 1,
                ]);
                $this->insertDetail((int) $request->id, $request->type, $details, $request->created_at, $request->updated_at);
            }
        });
    }

    private function backfillTickets(): void
    {
        DB::table('tickets')->whereNotNull('user_id')->whereNull('service_request_id')->orderBy('id')->chunkById(200, function ($tickets): void {
            foreach ($tickets as $ticket) {
                $fulfilment = match ($ticket->status) {
                    'in_progress' => 'provisioning',
                    'waiting_user' => 'queued',
                    'resolved', 'closed' => 'completed',
                    default => 'not_started',
                };
                $requestId = DB::table('service_requests')->insertGetId([
                    'code' => $ticket->ticket_code,
                    'requester_id' => $ticket->user_id,
                    'type' => 'support',
                    'schema_version' => 1,
                    'title' => $ticket->subject,
                    'description' => $ticket->description,
                    'status' => $ticket->status === 'resolved' ? 'completed' : ($ticket->status === 'in_progress' ? 'in_progress' : 'submitted'),
                    'approval_status' => 'approved',
                    'fulfilment_status' => $fulfilment,
                    'priority' => $ticket->priority === 'medium' ? 'normal' : $ticket->priority,
                    'details' => json_encode(['application' => 'Legacy application', 'severity' => $ticket->priority, 'category' => $ticket->category], JSON_THROW_ON_ERROR),
                    'currency' => 'USD',
                    'source' => 'legacy_ticket',
                    'source_record_id' => (string) $ticket->id,
                    'approval_source' => 'none',
                    'approval_sync_status' => 'synced',
                    'submitted_at' => $ticket->created_at,
                    'completed_at' => in_array($ticket->status, ['resolved', 'closed'], true) ? ($ticket->resolved_at ?: $ticket->updated_at) : null,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->updated_at,
                ]);
                DB::table('tickets')->where('id', $ticket->id)->update(['service_request_id' => $requestId]);
            }
        });
    }

    private function insertDetail(int $requestId, string $type, array $details, mixed $createdAt, mixed $updatedAt): void
    {
        [$table, $values] = match ($type) {
            'ai_token' => ['ai_access_request_details', ['purpose' => data_get($details, 'purpose'), 'models' => json_encode(data_get($details, 'models', [])), 'max_budget' => data_get($details, 'max_budget'), 'budget_duration' => data_get($details, 'budget_duration'), 'rpm_limit' => data_get($details, 'rpm_limit'), 'tpm_limit' => data_get($details, 'tpm_limit')]],
            'custom_system' => ['system_development_request_details', ['problem' => data_get($details, 'problem'), 'target_users' => data_get($details, 'target_users'), 'capabilities' => data_get($details, 'capabilities'), 'needs_ai_analyzer' => (bool) data_get($details, 'needs_ai_analyzer'), 'ai_models' => json_encode(data_get($details, 'ai_models', [])), 'ai_max_budget' => data_get($details, 'ai_max_budget')]],
            'integration' => ['integration_request_details', ['source_system' => data_get($details, 'source_system'), 'target_system' => data_get($details, 'target_system'), 'scope' => data_get($details, 'scope'), 'access_status' => data_get($details, 'access_status')]],
            'saas_subscription' => ['subscription_purchase_request_details', ['product' => data_get($details, 'product'), 'plan' => data_get($details, 'plan'), 'seats' => data_get($details, 'seats'), 'billing_cycle' => data_get($details, 'billing_cycle'), 'vendor_url' => data_get($details, 'vendor_url'), 'business_reason' => data_get($details, 'business_reason')]],
            default => [null, []],
        };
        if ($table) {
            DB::table($table)->insert(['service_request_id' => $requestId, ...$values, 'created_at' => $createdAt, 'updated_at' => $updatedAt]);
        }
    }

    private function states(string $status): array
    {
        return match ($status) {
            'under_review' => ['in_approval', 'not_started'],
            'revision_requested' => ['revision_required', 'not_started'],
            'approved' => ['approved', 'not_started'],
            'in_progress' => ['approved', 'provisioning'],
            'waiting_external' => ['approved', 'queued'],
            'completed' => ['approved', 'completed'],
            'rejected' => ['rejected', 'not_started'],
            'cancelled' => ['cancelled', 'not_started'],
            default => ['submitted', 'not_started'],
        };
    }
};
