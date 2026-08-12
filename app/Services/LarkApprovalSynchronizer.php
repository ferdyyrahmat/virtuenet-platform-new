<?php

namespace App\Services;

use App\Enums\RequestApprovalStatus;
use App\Enums\RequestFulfilmentStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestType;
use App\Jobs\ProvisionAiCredential;
use App\Models\ServiceRequest;
use App\Models\SystemNotification;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class LarkApprovalSynchronizer
{
    public function overall(ServiceRequest $request, string $larkStatus, array $metadata = []): ServiceRequest
    {
        $status = match (strtoupper($larkStatus)) {
            'APPROVED' => ServiceRequestStatus::Approved,
            'REJECTED' => ServiceRequestStatus::Rejected,
            'CANCELED', 'CANCELLED', 'REVERTED', 'DELETED' => ServiceRequestStatus::Cancelled,
            'PENDING' => ServiceRequestStatus::UnderReview,
            default => throw new UnexpectedValueException('Unknown Lark approval status.'),
        };

        $request = DB::transaction(function () use ($request, $larkStatus, $metadata, $status): ServiceRequest {
            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($request->id);
            $changed = $request->status !== $status;
            $request->update([
                'status' => $status,
                'approval_status' => match ($status) {
                    ServiceRequestStatus::Approved => RequestApprovalStatus::Approved,
                    ServiceRequestStatus::Rejected => RequestApprovalStatus::Rejected,
                    ServiceRequestStatus::Cancelled => RequestApprovalStatus::Cancelled,
                    default => RequestApprovalStatus::InApproval,
                },
                'fulfilment_status' => $status === ServiceRequestStatus::Approved && $request->fulfilment_status === RequestFulfilmentStatus::NotStarted
                    ? RequestFulfilmentStatus::Queued
                    : $request->fulfilment_status,
                'lark_status' => strtoupper($larkStatus) ?: 'PENDING',
                'approval_sync_status' => 'synced',
                'approval_synced_at' => now(),
                'approved_at' => $status === ServiceRequestStatus::Approved ? ($request->approved_at ?? now()) : $request->approved_at,
                'cancelled_at' => $status === ServiceRequestStatus::Cancelled ? ($request->cancelled_at ?? now()) : $request->cancelled_at,
            ]);

            if ($changed) {
                $request->updates()->create([
                    'type' => 'approval_sync',
                    'message' => 'Approval state synchronized from Lark: '.$status->label().'.',
                    'metadata' => $metadata ?: null,
                ]);
                SystemNotification::send(
                    $request->requester_id,
                    'Request '.$status->label(),
                    $request->code.' was updated from Lark Approval.',
                    $status === ServiceRequestStatus::Approved ? 'success' : 'info',
                    'mdi-clipboard-check-outline',
                    route('v1.requests.show', $request)
                );
            }

            return $request;
        });

        if ($status === ServiceRequestStatus::Approved
            && ($request->type === ServiceRequestType::AiToken || data_get($request->details, 'needs_ai_analyzer'))
            && ! $request->aiCredential()->exists()) {
            ProvisionAiCredential::dispatch($request)->afterCommit();
        }

        return $request;
    }

    public function task(ServiceRequest $request, array $event): void
    {
        $status = match (strtoupper((string) data_get($event, 'status'))) {
            'APPROVED', 'DONE' => 'approved',
            'REJECTED' => 'rejected',
            'TRANSFERRED' => 'pending',
            'ROLLBACK' => 'revision_requested',
            default => null,
        };
        if (! $status) {
            return;
        }

        $approval = $request->approvals()
            ->when(data_get($event, 'task_id'), fn ($query, $taskId) => $query->where('lark_task_id', $taskId))
            ->when(! data_get($event, 'task_id') && data_get($event, 'node_id'), fn ($query) => $query->where('lark_node_id', data_get($event, 'node_id')))
            ->first();
        $approval?->update([
            'status' => $status,
            'lark_task_id' => data_get($event, 'task_id') ?: $approval->lark_task_id,
            'external_approver_id' => data_get($event, 'open_id'),
            'acted_at' => now(),
        ]);
    }
}
