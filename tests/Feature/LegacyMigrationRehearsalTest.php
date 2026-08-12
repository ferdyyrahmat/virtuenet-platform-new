<?php

namespace Tests\Feature;

use App\Models\AiAccessCredential;
use App\Models\ServiceRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyMigrationRehearsalTest extends TestCase
{
    use RefreshDatabase;

    public function test_cutover_gate_fails_closed_without_exposing_configuration_secrets(): void
    {
        config()->set('app.key', 'base64:cutover-secret-that-must-not-be-printed');

        $this->assertSame(1, Artisan::call('platform:cutover-check', ['--json' => true]));
        $output = Artisan::output();
        $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        $this->assertFalse($payload['ready']);
        $this->assertFalse($payload['checks']['production_environment']);
        $this->assertStringNotContainsString('cutover-secret', $output);
    }

    public function test_legacy_import_is_idempotent_preserves_meaning_and_never_reopens_token_delivery(): void
    {
        $this->legacySchema();
        $legacy = DB::connection('legacy_virtuenet');
        $legacy->table('departments')->insert(['id' => '00000000-0000-0000-0000-000000000001', 'slug' => 'it', 'name' => 'IT', 'is_active' => true]);
        $legacy->table('users')->insert(['id' => '00000000-0000-0000-0000-000000000002', 'email' => 'legacy@example.com', 'lark_name' => 'Legacy User', 'lark_open_id' => 'ou_legacy', 'avatar_url' => null]);
        $legacy->table('dept_members')->insert(['user_id' => '00000000-0000-0000-0000-000000000002', 'dept_id' => '00000000-0000-0000-0000-000000000001', 'is_primary' => true]);
        $legacy->table('service_requests')->insert([
            'id' => '00000000-0000-0000-0000-000000000003', 'requester_id' => '00000000-0000-0000-0000-000000000002',
            'dept_id' => '00000000-0000-0000-0000-000000000001', 'sro_number' => 'SRO-LEGACY-001', 'service_type' => 'ai_token',
            'service_name' => 'Legacy AI access', 'description' => 'Migrated AI access.', 'status' => 'live',
            'business_justification' => 'Operational continuity', 'requested_subdomain' => null, 'requires_ai' => true,
            'ai_features' => '["analysis"]', 'target_integrations' => '[]', 'external_endpoints' => '[]',
            'submitted_at' => now()->subMonth(), 'approved_at' => now()->subWeeks(3), 'deployed_at' => now()->subWeeks(2),
        ]);
        $legacy->table('ai_virtual_keys')->insert([
            'id' => '00000000-0000-0000-0000-000000000004', 'user_id' => '00000000-0000-0000-0000-000000000002',
            'sro_id' => '00000000-0000-0000-0000-000000000003', 'key_alias' => 'legacy-analyzer', 'virtual_key' => 'sk-legacy-secret-value',
            'litellm_key_id' => 'key-legacy', 'allowed_models' => '["gpt-5-mini"]', 'max_budget_usd' => 50,
            'spent_usd' => 12.5, 'rpm_limit' => 20, 'tpm_limit' => 10000, 'status' => 'active', 'expires_at' => now()->addMonth(),
        ]);

        $exitCode = Artisan::call('platform:import-legacy', ['--commit' => true]);
        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(0, Artisan::call('platform:import-legacy', ['--commit' => true]));

        $request = ServiceRequest::firstOrFail();
        $credential = AiAccessCredential::firstOrFail();
        $this->assertSame('ai_token', $request->type->value);
        $this->assertSame('completed', $request->status->value);
        $this->assertSame('approved', $request->approval_status->value);
        $this->assertSame('completed', $request->fulfilment_status->value);
        $this->assertNull($credential->reveal_expires_at);
        $this->assertNotNull($credential->revealed_at);
        $this->assertSame('sk-legacy-secret-value', $credential->virtual_key);
        $this->assertNotSame('sk-legacy-secret-value', DB::table('ai_access_credentials')->value('virtual_key'));
        $this->assertDatabaseCount('service_requests', 1);
        $this->assertDatabaseCount('ai_access_credentials', 1);
    }

    private function legacySchema(): void
    {
        config()->set('database.connections.legacy_virtuenet', ['driver' => 'sqlite', 'url' => 'sqlite:///:memory:', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('legacy_virtuenet');
        $schema = Schema::connection('legacy_virtuenet');
        $schema->create('departments', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('slug');
            $table->string('name');
            $table->boolean('is_active');
        });
        $schema->create('users', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('email');
            $table->string('lark_name');
            $table->string('lark_open_id')->nullable();
            $table->string('avatar_url')->nullable();
        });
        $schema->create('dept_members', function (Blueprint $table): void {
            $table->string('user_id');
            $table->string('dept_id');
            $table->boolean('is_primary');
        });
        $schema->create('service_requests', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('requester_id');
            $table->string('dept_id');
            $table->string('sro_number');
            $table->string('service_type');
            $table->string('service_name');
            $table->text('description');
            $table->string('status');
            $table->text('business_justification')->nullable();
            $table->string('requested_subdomain')->nullable();
            $table->boolean('requires_ai');
            $table->text('ai_features')->nullable();
            $table->text('target_integrations')->nullable();
            $table->text('external_endpoints')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('deployed_at')->nullable();
        });
        $schema->create('approval_records', fn (Blueprint $table) => $table->string('id')->primary());
        $schema->create('approval_stages', fn (Blueprint $table) => $table->string('id')->primary());
        $schema->create('ai_virtual_keys', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('sro_id')->nullable();
            $table->string('key_alias');
            $table->text('virtual_key');
            $table->string('litellm_key_id')->nullable();
            $table->text('allowed_models')->nullable();
            $table->decimal('max_budget_usd', 12, 4);
            $table->decimal('spent_usd', 12, 4);
            $table->integer('rpm_limit')->nullable();
            $table->integer('tpm_limit')->nullable();
            $table->string('status');
            $table->timestamp('expires_at')->nullable();
        });
    }
}
