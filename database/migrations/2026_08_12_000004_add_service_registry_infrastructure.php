<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vps_nodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('legacy_uuid')->nullable()->unique();
            $table->string('name', 100);
            $table->string('cluster_key', 100)->unique();
            $table->string('hostname')->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('account_type', 30)->default('it_shared');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coolify_server_uuid', 100)->nullable()->unique();
            $table->unsignedSmallInteger('cpu_cores')->nullable();
            $table->unsignedSmallInteger('ram_gb')->nullable();
            $table->unsignedInteger('disk_gb')->nullable();
            $table->decimal('cpu_usage_percent', 5, 2)->nullable();
            $table->decimal('ram_usage_percent', 5, 2)->nullable();
            $table->decimal('disk_usage_percent', 5, 2)->nullable();
            $table->timestamp('last_reported_at')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('github_deployed_repos', function (Blueprint $table) {
            $table->string('domain')->nullable()->default(null)->change();
            $table->foreignId('vps_node_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->after('vps_node_id')->constrained('users')->nullOnDelete();
            $table->string('coolify_uuid', 100)->nullable()->unique()->after('domain');
            $table->string('environment', 20)->default('virtuenet')->after('coolify_uuid');
            $table->string('health_path', 255)->default('/health')->after('health_status');
            $table->string('runtime_status', 50)->default('unknown')->after('health_path');
            $table->string('thumbnail_url', 2048)->nullable()->after('runtime_status');
            $table->text('domain_exception_reason')->nullable()->after('cost_center');
            $table->timestamp('deployed_at')->nullable()->after('last_checked_at');
            $table->timestamp('offline_since')->nullable()->after('deployed_at');
            $table->timestamp('last_recovered_at')->nullable()->after('offline_since');
        });

        Schema::create('service_health_checks', function (Blueprint $table) {
            $table->id();
            $table->string('repo_full_name');
            $table->string('status', 30);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamp('checked_at')->index();
            $table->foreign('repo_full_name')->references('repo_full_name')->on('github_deployed_repos')->cascadeOnDelete();
            $table->index(['repo_full_name', 'checked_at']);
        });

        Schema::create('service_uptime_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('repo_full_name');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('last_status', 30)->default('offline');
            $table->timestamps();
            $table->foreign('repo_full_name')->references('repo_full_name')->on('github_deployed_repos')->cascadeOnDelete();
            $table->index(['repo_full_name', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_uptime_incidents');
        Schema::dropIfExists('service_health_checks');
        DB::table('github_deployed_repos')->whereNull('domain')->update(['domain' => '']);
        Schema::table('github_deployed_repos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropConstrainedForeignId('vps_node_id');
            $table->dropColumn(['coolify_uuid', 'environment', 'health_path', 'runtime_status', 'thumbnail_url', 'domain_exception_reason', 'deployed_at', 'offline_since', 'last_recovered_at']);
            $table->string('domain')->nullable(false)->default('')->change();
        });
        Schema::dropIfExists('vps_nodes');
    }
};
