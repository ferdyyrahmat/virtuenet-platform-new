<?php

namespace App\Services;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestType;
use App\Models\Department;
use App\Models\LarkApprovalContract;
use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnexpectedValueException;

class LarkApprovalIngestor
{
    public function __construct(
        private readonly LarkService $lark,
        private readonly LarkApprovalSynchronizer $synchronizer,
    ) {}

    public function ingest(string $instanceCode): ServiceRequest
    {
        $instance = $this->lark->approvalInstance($instanceCode);
        $approvalCode = (string) data_get($instance, 'approval_code');
        $expectedCode = (string) config('services.lark.approval_code');
        if ($approvalCode === '' || ! hash_equals($expectedCode, $approvalCode)) {
            throw new UnexpectedValueException('Unsupported Lark approval definition.');
        }

        $form = $this->decodeForm(data_get($instance, 'form'));
        $tasks = array_values(array_filter((array) data_get($instance, 'task_list', []), 'is_array'));
        $contractHash = $this->guardContract($approvalCode, $form, $tasks);
        $requester = User::query()->where('lark_open_id', data_get($instance, 'open_id'))->first();
        if (! $requester) {
            throw new UnexpectedValueException('Lark requester is not linked to a platform user.');
        }

        $type = $this->requestType($form);
        $this->requestStatus((string) data_get($instance, 'status'));
        $request = DB::transaction(function () use ($instance, $instanceCode, $approvalCode, $form, $tasks, $contractHash, $requester, $type): ServiceRequest {
            $serial = trim((string) data_get($instance, 'serial_number'));
            $existing = ServiceRequest::query()->where('source', 'lark')->where('source_record_id', $instanceCode)->first();
            $request = ServiceRequest::query()->updateOrCreate(
                ['source' => 'lark', 'source_record_id' => $instanceCode],
                [
                    'code' => $serial !== '' ? 'LARK-'.$serial : 'LARK-'.strtoupper(substr($instanceCode, -12)),
                    'parent_id' => $this->parentId($instance),
                    'requester_id' => $requester->id,
                    'department_id' => Department::query()->where('lark_department_id', data_get($instance, 'department_id'))->value('id'),
                    'type' => $type,
                    'title' => $this->formValue($form, ['request title', 'judul permintaan', 'system name', 'nama sistem', 'application name', 'nama aplikasi'])
                        ?: $type->label().' '.$serial,
                    'description' => $this->formValue($form, ['description', 'deskripsi', 'business justification', 'justifikasi', 'purpose', 'tujuan', 'requirement', 'kebutuhan'])
                        ?: 'Imported from Lark Modified approval.',
                    'status' => $existing?->status ?? ServiceRequestStatus::UnderReview,
                    'current_stage' => $this->currentStage($tasks),
                    'details' => ['lark_serial_number' => $serial],
                    'source' => 'lark',
                    'source_record_id' => $instanceCode,
                    'approval_source' => 'lark',
                    'lark_approval_code' => $approvalCode,
                    'lark_instance_code' => $instanceCode,
                    'lark_status' => strtoupper((string) data_get($instance, 'status')),
                    'lark_approval_url' => $this->approvalUrl($approvalCode, $instanceCode),
                    'lark_contract_hash' => $contractHash,
                    'lark_form_snapshot' => $form,
                    'approval_sync_status' => 'synced',
                    'approval_synced_at' => now(),
                    'submitted_at' => $this->timestamp(data_get($instance, 'start_time')) ?? now(),
                    'source_created_at' => $this->timestamp(data_get($instance, 'start_time')),
                    'source_updated_at' => $this->timestamp(data_get($instance, 'end_time')) ?? now(),
                ]
            );

            foreach ($tasks as $index => $task) {
                $request->approvals()->updateOrCreate(
                    ['lark_task_id' => (string) (data_get($task, 'id') ?: $instanceCode.'-'.$index)],
                    [
                        'round' => 1,
                        'step' => $index + 1,
                        'stage' => (string) (data_get($task, 'node_name') ?: data_get($task, 'custom_node_id') ?: 'Lark approval'),
                        'status' => $this->taskStatus((string) data_get($task, 'status')),
                        'lark_node_id' => (string) (data_get($task, 'custom_node_id') ?: data_get($task, 'node_id')),
                        'external_approver_id' => (string) (data_get($task, 'open_id') ?: data_get($task, 'user_id')),
                        'acted_at' => $this->timestamp(data_get($task, 'end_time')),
                    ]
                );
            }

            if ($request->wasRecentlyCreated) {
                $request->updates()->create(['type' => 'imported', 'message' => 'Request imported from Lark Modified approval.']);
            }

            return $request;
        });

        return $this->synchronizer->overall($request, (string) data_get($instance, 'status'), ['source' => 'lark_api']);
    }

    private function guardContract(string $approvalCode, array $form, array $tasks): string
    {
        $controls = collect($form)->map(fn (array $field): array => Arr::only($field, ['id', 'custom_id', 'name', 'type']))
            ->sortBy(fn (array $field): string => (string) ($field['custom_id'] ?? $field['id'] ?? ''))->values()->all();
        $nodes = collect($tasks)->map(fn (array $task): array => Arr::only($task, ['node_id', 'custom_node_id', 'node_name', 'type']))
            ->unique(fn (array $node): string => (string) ($node['custom_node_id'] ?? $node['node_id'] ?? ''))
            ->sortBy(fn (array $node): string => (string) ($node['custom_node_id'] ?? $node['node_id'] ?? ''))->values()->all();
        $hash = hash('sha256', json_encode(compact('controls', 'nodes'), JSON_THROW_ON_ERROR));

        $contract = LarkApprovalContract::query()->firstOrCreate(
            ['approval_code' => $approvalCode, 'contract_hash' => $hash],
            ['controls' => $controls, 'nodes' => $nodes, 'active' => false, 'observed_at' => now()]
        );
        $active = LarkApprovalContract::query()->where('approval_code', $approvalCode)->where('active', true)->first();
        if (! $active) {
            $contract->update(['active' => true]);
        } elseif (! hash_equals($active->contract_hash, $hash)) {
            throw new UnexpectedValueException('Lark Modified contract drift detected.');
        }

        return $hash;
    }

    private function decodeForm(mixed $form): array
    {
        $decoded = is_string($form) ? json_decode($form, true) : $form;
        if (! is_array($decoded)) {
            throw new UnexpectedValueException('Lark approval form is invalid.');
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    private function requestType(array $form): ServiceRequestType
    {
        $value = Str::lower((string) $this->formValue($form, ['request type', 'service type', 'jenis permintaan', 'jenis layanan', 'category', 'kategori']));

        return match (true) {
            str_contains($value, 'ai') && (str_contains($value, 'token') || str_contains($value, 'access')) => ServiceRequestType::AiToken,
            str_contains($value, 'integrat') || str_contains($value, 'integrasi') => ServiceRequestType::Integration,
            str_contains($value, 'subscription') || str_contains($value, 'saas') || str_contains($value, 'langganan') => ServiceRequestType::SaasSubscription,
            str_contains($value, 'custom') || str_contains($value, 'development') || str_contains($value, 'pembuatan sistem') || str_contains($value, 'aplikasi') => ServiceRequestType::CustomSystem,
            default => throw new UnexpectedValueException('Lark request category cannot be mapped safely.'),
        };
    }

    private function formValue(array $form, array $aliases): mixed
    {
        foreach ($form as $field) {
            $identity = Str::of(trim(implode(' ', Arr::only($field, ['custom_id', 'name']))))
                ->lower()->replace(['_', '-'], ' ')->squish()->toString();
            if (collect($aliases)->contains(fn (string $alias): bool => str_contains($identity, $alias))) {
                $value = data_get($field, 'value');

                return is_array($value) ? implode(', ', Arr::flatten($value)) : $value;
            }
        }

        return null;
    }

    private function requestStatus(string $status): ServiceRequestStatus
    {
        return match (strtoupper($status)) {
            'PENDING' => ServiceRequestStatus::UnderReview,
            'APPROVED' => ServiceRequestStatus::Approved,
            'REJECTED' => ServiceRequestStatus::Rejected,
            'CANCELED', 'CANCELLED', 'REVERTED', 'DELETED' => ServiceRequestStatus::Cancelled,
            default => throw new UnexpectedValueException('Unknown Lark approval status.'),
        };
    }

    private function taskStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PENDING', 'TRANSFERRED' => 'pending',
            'APPROVED', 'DONE' => 'approved',
            'REJECTED' => 'rejected',
            'ROLLBACK' => 'revision_requested',
            default => 'pending',
        };
    }

    private function currentStage(array $tasks): ?string
    {
        $pending = collect($tasks)->first(fn (array $task): bool => in_array(strtoupper((string) data_get($task, 'status')), ['PENDING', 'TRANSFERRED'], true));

        return $pending ? (string) (data_get($pending, 'node_name') ?: data_get($pending, 'custom_node_id')) : null;
    }

    private function parentId(array $instance): ?int
    {
        $parentCode = data_get($instance, 'modified_instance_code') ?: data_get($instance, 'reverted_instance_code');

        return $parentCode ? ServiceRequest::query()->where('source', 'lark')->where('source_record_id', $parentCode)->value('id') : null;
    }

    private function approvalUrl(string $approvalCode, string $instanceCode): ?string
    {
        $template = config('services.lark.approval_url_template');

        return $template ? strtr($template, ['{approval_code}' => $approvalCode, '{instance_code}' => $instanceCode]) : null;
    }

    private function timestamp(mixed $milliseconds): ?CarbonImmutable
    {
        return is_numeric($milliseconds) && (int) $milliseconds > 0
            ? CarbonImmutable::createFromTimestampMs((int) $milliseconds)
            : null;
    }
}
