<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('github_deployed_repos')) {
            Schema::create('github_deployed_repos', function (Blueprint $table) {
                $table->string('repo_full_name')->primary();
                $table->string('display_name')->default('');
                $table->string('domain')->default('');
                $table->boolean('sync_enabled')->default(true)->index();
                $table->boolean('backup_enabled')->default(true)->index();
                $table->text('notes')->nullable();
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->string('cost_center')->nullable()->index();
                $table->string('health_status')->default('unknown')->index();
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->unsignedInteger('response_time_ms')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamp('updated_at')->useCurrent();
            });
        } else {
            Schema::table('github_deployed_repos', function (Blueprint $table) {
                if (! Schema::hasColumn('github_deployed_repos', 'department_id')) {
                    $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('github_deployed_repos', 'cost_center')) {
                    $table->string('cost_center')->nullable()->index();
                }
                if (! Schema::hasColumn('github_deployed_repos', 'health_status')) {
                    $table->string('health_status')->default('unknown')->index();
                    $table->unsignedSmallInteger('http_status')->nullable();
                    $table->unsignedInteger('response_time_ms')->nullable();
                    $table->timestamp('last_checked_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('github_developer_mappings')) {
            Schema::create('github_developer_mappings', function (Blueprint $table) {
                $table->string('github_username', 100)->primary();
                $table->string('github_user_id', 100)->nullable();
                $table->string('lark_email')->nullable();
                $table->string('lark_name')->nullable();
                $table->string('lark_open_id', 100);
                $table->string('lark_section_guid', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('github_lark_task_mappings')) {
            Schema::create('github_lark_task_mappings', function (Blueprint $table) {
                $table->string('github_issue_url', 500)->primary();
                $table->string('lark_task_guid', 100);
                $table->string('lark_user_id', 100)->nullable();
                $table->string('repo_full_name')->nullable()->index();
                $table->unsignedInteger('github_issue_number')->nullable();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('github_repo_main_tasks')) {
            Schema::create('github_repo_main_tasks', function (Blueprint $table) {
                $table->string('repo_full_name');
                $table->string('lark_user_id', 100);
                $table->string('main_task_guid', 100);
                $table->timestamp('updated_at')->useCurrent();
                $table->primary(['repo_full_name', 'lark_user_id']);
            });
        }

        Schema::table('github_tasks', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('mandays', 8, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('github_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('mandays');
        });
        if (Schema::hasTable('github_deployed_repos')) {
            Schema::table('github_deployed_repos', function (Blueprint $table) {
                if (Schema::hasColumn('github_deployed_repos', 'department_id')) {
                    $table->dropConstrainedForeignId('department_id');
                }
                $table->dropColumn(array_values(array_filter(['cost_center', 'health_status', 'http_status', 'response_time_ms', 'last_checked_at'], fn (string $column): bool => Schema::hasColumn('github_deployed_repos', $column))));
            });
        }
    }
};
