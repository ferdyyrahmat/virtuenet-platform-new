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
        $integrationEvents = DB::table('integration_events')->get(['id', 'payload']);
        Schema::table('integration_events', fn (Blueprint $table) => $table->longText('payload')->change());
        foreach ($integrationEvents as $event) {
            $payload = is_string($event->payload) ? $event->payload : json_encode($event->payload, JSON_THROW_ON_ERROR);
            DB::table('integration_events')->where('id', $event->id)->update(['payload' => Crypt::encryptString($payload)]);
        }

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->uuid('legacy_uuid')->nullable()->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('lark_department_id')->nullable()->unique();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('department_user', function (Blueprint $table) {
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->primary(['department_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('legacy_uuid')->nullable()->unique()->after('id');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->uuid('legacy_uuid')->nullable()->unique()->after('id');
            $table->foreignId('department_id')->nullable()->after('requester_id')->constrained()->nullOnDelete();
            $table->string('source')->default('platform')->index();
            $table->string('source_record_id')->nullable()->index();
            $table->string('approval_source')->default('local')->index();
            $table->string('lark_approval_code')->nullable();
            $table->string('lark_instance_code')->nullable()->unique();
            $table->string('lark_status')->nullable()->index();
            $table->string('lark_approval_url', 2048)->nullable();
            $table->string('lark_contract_hash', 64)->nullable()->index();
            $table->json('lark_form_snapshot')->nullable();
            $table->string('approval_sync_status')->default('pending')->index();
            $table->timestamp('approval_synced_at')->nullable();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->unique(['source', 'source_record_id']);
        });

        Schema::create('lark_approval_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('approval_code')->index();
            $table->string('contract_hash', 64);
            $table->json('controls');
            $table->json('nodes');
            $table->boolean('active')->default(false)->index();
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->unique(['approval_code', 'contract_hash']);
        });

        Schema::table('service_request_approvals', function (Blueprint $table) {
            $table->string('lark_node_id')->nullable()->index();
            $table->string('lark_task_id')->nullable()->unique();
            $table->string('external_approver_id')->nullable()->index();
        });

        Schema::table('ai_access_credentials', function (Blueprint $table) {
            $table->uuid('legacy_uuid')->nullable()->unique()->after('id');
            $table->string('gateway_key_id')->nullable()->index();
            $table->timestamp('reveal_expires_at')->nullable();
            $table->timestamp('revealed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_access_credentials', function (Blueprint $table) {
            $table->dropColumn(['legacy_uuid', 'gateway_key_id', 'reveal_expires_at', 'revealed_at', 'revoked_at']);
        });
        Schema::table('service_request_approvals', function (Blueprint $table) {
            $table->dropColumn(['lark_node_id', 'lark_task_id', 'external_approver_id']);
        });
        Schema::dropIfExists('lark_approval_contracts');
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn([
                'legacy_uuid', 'source', 'source_record_id', 'approval_source', 'lark_approval_code',
                'lark_instance_code', 'lark_status', 'approval_sync_status', 'approval_synced_at',
                'lark_approval_url', 'lark_contract_hash', 'lark_form_snapshot',
                'source_created_at', 'source_updated_at',
            ]);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('legacy_uuid'));
        Schema::dropIfExists('department_user');
        Schema::dropIfExists('departments');

        $integrationEvents = DB::table('integration_events')->get(['id', 'payload']);
        foreach ($integrationEvents as $event) {
            DB::table('integration_events')->where('id', $event->id)->update(['payload' => Crypt::decryptString($event->payload)]);
        }
        Schema::table('integration_events', fn (Blueprint $table) => $table->json('payload')->change());
    }
};
