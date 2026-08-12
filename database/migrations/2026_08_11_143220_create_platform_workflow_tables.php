<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('service_requests')->nullOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->index();
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('submitted')->index();
            $table->string('priority')->default('normal')->index();
            $table->string('current_stage')->nullable();
            $table->json('details');
            $table->decimal('estimated_budget', 15, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->date('requested_due_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['requester_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('service_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->unsignedTinyInteger('step');
            $table->string('stage');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->text('note')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->unique(['service_request_id', 'round', 'step'], 'request_approval_round_step_unique');
        });

        Schema::create('service_request_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->index();
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['service_request_id', 'created_at']);
        });

        Schema::create('service_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('reference')->nullable();
            $table->string('access_url', 2048)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_access_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('external_user_id')->index();
            $table->string('key_alias');
            $table->text('virtual_key');
            $table->char('key_hash', 64)->unique();
            $table->string('key_preview');
            $table->json('models')->nullable();
            $table->decimal('max_budget', 15, 4)->nullable();
            $table->decimal('current_spend', 15, 4)->default(0);
            $table->string('budget_duration')->nullable();
            $table->unsignedInteger('rpm_limit')->nullable();
            $table->unsignedInteger('tpm_limit')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('external_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('label');
            $table->string('base_url', 2048)->nullable();
            $table->longText('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('enabled')->default(false)->index();
            $table->string('health_status')->default('unknown')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('github_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('repository');
            $table->unsignedBigInteger('issue_number');
            $table->string('github_node_id')->nullable();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('state')->index();
            $table->json('labels')->nullable();
            $table->string('assignee')->nullable();
            $table->string('github_url', 2048);
            $table->string('lark_task_id')->nullable()->index();
            $table->string('lark_task_url', 2048)->nullable();
            $table->string('sync_status')->default('pending')->index();
            $table->text('sync_error')->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['repository', 'issue_number']);
            $table->index(['repository', 'state']);
        });

        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('external_id');
            $table->string('event_type')->index();
            $table->json('payload');
            $table->string('status')->default('received')->index();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('github_tasks');
        Schema::dropIfExists('external_connections');
        Schema::dropIfExists('ai_access_credentials');
        Schema::dropIfExists('service_deliveries');
        Schema::dropIfExists('service_request_updates');
        Schema::dropIfExists('service_request_approvals');
        Schema::dropIfExists('service_requests');
    }
};
