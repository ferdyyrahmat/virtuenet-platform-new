<?php

namespace App\Console\Commands;

use App\Models\AiAccessCredential;
use App\Models\Department;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class ImportLegacyPlatform extends Command
{
    protected $signature = 'platform:import-legacy {--commit : Persist the import; without this flag only counts are shown}';

    protected $description = 'Idempotently import UUID records from the legacy VirtueNet PostgreSQL database';

    public function handle(): int
    {
        if (blank(config('database.connections.legacy_virtuenet.url'))) {
            $this->error('Set LEGACY_DB_URL to the read-only legacy PostgreSQL connection.');

            return self::FAILURE;
        }

        $tables = ['departments', 'users', 'dept_members', 'service_requests', 'approval_records', 'approval_stages', 'ai_virtual_keys'];
        $missing = collect($tables)->reject(fn (string $table): bool => Schema::connection('legacy_virtuenet')->hasTable($table));
        if ($missing->isNotEmpty()) {
            $this->error('Legacy schema is missing: '.$missing->join(', '));

            return self::FAILURE;
        }

        if (! $this->option('commit')) {
            $this->table(['Legacy table', 'Rows'], collect($tables)->map(fn (string $table): array => [
                $table, DB::connection('legacy_virtuenet')->table($table)->count(),
            ]));
            $this->warn('Dry run only. Re-run with --commit to persist the idempotent import.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            $this->importDepartments();
            $this->importUsers();
            $this->importMemberships();
            $this->importRequests();
            $this->importApprovals();
            $this->importAiCredentials();
        });

        $this->info('Legacy platform data imported successfully.');

        return self::SUCCESS;
    }

    private function importDepartments(): void
    {
        foreach ($this->legacy('departments') as $row) {
            Department::updateOrCreate(['legacy_uuid' => $row->id], [
                'code' => $row->slug,
                'name' => $row->name,
                'active' => $row->is_active,
            ]);
        }
    }

    private function importUsers(): void
    {
        foreach ($this->legacy('users') as $row) {
            $user = User::firstOrNew(['email' => $row->email]);
            $user->fill([
                'legacy_uuid' => $row->id,
                'name' => $row->lark_name,
                'lark_open_id' => $row->lark_open_id,
                'avatar' => $row->avatar_url,
            ]);
            if (! $user->exists) {
                $user->password = Hash::make(Str::random(64));
            }
            $user->save();
        }
    }

    private function importMemberships(): void
    {
        foreach ($this->legacy('dept_members') as $row) {
            $userId = User::where('legacy_uuid', $row->user_id)->value('id');
            $departmentId = Department::where('legacy_uuid', $row->dept_id)->value('id');
            if ($userId && $departmentId) {
                DB::table('department_user')->updateOrInsert(
                    ['user_id' => $userId, 'department_id' => $departmentId],
                    ['is_primary' => $row->is_primary, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    private function importRequests(): void
    {
        foreach ($this->legacy('service_requests') as $row) {
            $requesterId = User::where('legacy_uuid', $row->requester_id)->value('id');
            $departmentId = Department::where('legacy_uuid', $row->dept_id)->value('id');
            if (! $requesterId || ! $departmentId) {
                throw new RuntimeException('Unmapped requester or department for legacy request '.$row->id);
            }

            ServiceRequest::updateOrCreate(['legacy_uuid' => $row->id], [
                'code' => $row->sro_number,
                'requester_id' => $requesterId,
                'department_id' => $departmentId,
                'type' => $this->requestType($row->service_type),
                'title' => $row->service_name,
                'description' => $row->description,
                'status' => $this->requestStatus($row->status),
                'details' => [
                    'business_justification' => $row->business_justification,
                    'requested_subdomain' => $row->requested_subdomain,
                    'requires_ai' => $row->requires_ai,
                    'ai_features' => $this->json($row->ai_features),
                    'target_integrations' => $this->json($row->target_integrations),
                    'external_endpoints' => $this->json($row->external_endpoints),
                ],
                'source' => 'legacy_postgres',
                'source_record_id' => $row->id,
                'approval_source' => 'lark',
                'submitted_at' => $row->submitted_at,
                'approved_at' => $row->approved_at,
                'completed_at' => $row->deployed_at,
            ]);
        }
    }

    private function importAiCredentials(): void
    {
        foreach ($this->legacy('ai_virtual_keys') as $row) {
            $userId = User::where('legacy_uuid', $row->user_id)->value('id');
            $requestId = $row->sro_id ? ServiceRequest::where('legacy_uuid', $row->sro_id)->value('id') : null;
            if (! $userId || ! $requestId) {
                continue;
            }

            AiAccessCredential::updateOrCreate(['legacy_uuid' => $row->id], [
                'service_request_id' => $requestId,
                'user_id' => $userId,
                'external_user_id' => 'virtuenet-user-'.$userId,
                'key_alias' => $row->key_alias,
                'virtual_key' => $row->virtual_key,
                'key_hash' => hash('sha256', $row->virtual_key),
                'key_preview' => substr($row->virtual_key, 0, 7).'...'.substr($row->virtual_key, -4),
                'gateway_key_id' => $row->litellm_key_id,
                'models' => $this->json($row->allowed_models),
                'max_budget' => $row->max_budget_usd,
                'current_spend' => $row->spent_usd,
                'rpm_limit' => $row->rpm_limit,
                'tpm_limit' => $row->tpm_limit,
                'status' => $row->status,
                'expires_at' => $row->expires_at,
                'reveal_expires_at' => now()->addDay(),
            ]);
        }
    }

    private function importApprovals(): void
    {
        foreach ($this->legacy('approval_records') as $record) {
            $request = ServiceRequest::where('legacy_uuid', $record->sro_id)->first();
            if (! $request) {
                continue;
            }

            $request->update([
                'approval_source' => 'lark',
                'lark_approval_code' => $record->lark_approval_code,
                'lark_instance_code' => $record->lark_instance_code,
                'lark_status' => strtoupper((string) $record->overall_status),
                'approval_sync_status' => $record->lark_instance_code ? 'synced' : 'pending',
                'approval_synced_at' => $record->updated_at,
            ]);

            foreach (DB::connection('legacy_virtuenet')->table('approval_stages')->where('approval_id', $record->id)->orderBy('stage_order')->cursor() as $stage) {
                $request->approvals()->updateOrCreate(
                    ['round' => 1, 'step' => $stage->stage_order],
                    [
                        'stage' => match ($stage->stage) {
                            'manager' => 'Manager approval',
                            'it_review' => 'IT technical review',
                            default => 'Business Strategy authorization',
                        },
                        'status' => $stage->status,
                        'note' => $stage->comment ?: $stage->revision_notes,
                        'acted_at' => $stage->decided_at,
                        'lark_node_id' => null,
                        'lark_task_id' => $stage->lark_task_id,
                        'external_approver_id' => $stage->approver_lark_id,
                    ]
                );
            }
        }
    }

    private function legacy(string $table): iterable
    {
        return DB::connection('legacy_virtuenet')->table($table)->orderBy('id')->cursor();
    }

    private function json(mixed $value): mixed
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }

    private function requestType(string $type): string
    {
        return match ($type) {
            'integration', 'middleware' => 'integration',
            'ai_powered' => 'custom_system',
            default => 'custom_system',
        };
    }

    private function requestStatus(string $status): string
    {
        return match ($status) {
            'draft', 'submitted' => 'submitted',
            'in_approval', 'pending_it_review', 'pending_bizstrat' => 'under_review',
            'revision_needed' => 'revision_requested',
            'approved', 'srs_generating', 'srs_ready', 'srs_validated' => 'approved',
            'deploy_queued', 'deploying' => 'in_progress',
            'deployed', 'live' => 'completed',
            'rejected' => 'rejected',
            'recalled', 'archived' => 'cancelled',
            'deploy_failed' => 'waiting_external',
            default => 'submitted',
        };
    }
}
