<?php

namespace App\Services;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestType;
use App\Jobs\CreateLarkApproval;
use App\Jobs\ProvisionAiCredential;
use App\Models\ServiceDelivery;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestApproval;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceRequestWorkflow
{
    public function create(User $requester, array $data): ServiceRequest
    {
        if (config('services.lark.approval_enabled') && ! config('services.lark.approval_outbound_enabled')) {
            throw ValidationException::withMessages(['request' => 'Submit this request from the Lark Modified approval form.']);
        }

        $request = DB::transaction(function () use ($requester, $data): ServiceRequest {
            $type = ServiceRequestType::from($data['type']);
            $larkApproval = (bool) config('services.lark.approval_enabled')
                && (bool) config('services.lark.approval_outbound_enabled');
            $request = ServiceRequest::create([
                ...$data,
                'code' => 'REQ-'.now()->format('Ym').'-'.strtoupper(substr((string) Str::ulid(), -8)),
                'requester_id' => $requester->id,
                'department_id' => $requester->departments()->wherePivot('is_primary', true)->value('departments.id')
                    ?? $requester->departments()->value('departments.id'),
                'status' => ServiceRequestStatus::Submitted,
                'approval_source' => $larkApproval ? 'lark' : 'local',
                'lark_approval_code' => $larkApproval ? config('services.lark.approval_code') : null,
                'current_stage' => $larkApproval ? 'Manager approval' : $type->approvalStages()[0],
                'submitted_at' => now(),
            ]);

            if (! $larkApproval) {
                $this->createApprovalRound($request, 1);
            }
            $this->record($request, $requester, 'submitted', 'Request submitted for review.');
            $this->notifyReviewers($request, 'New request '.$request->code, $request->title);

            return $request;
        });

        if ($request->approval_source === 'lark') {
            CreateLarkApproval::dispatch($request)->afterCommit();
        }

        return $request;
    }

    public function review(ServiceRequest $request, User $actor, string $action, ?string $note): ServiceRequest
    {
        if ($request->approval_source === 'lark') {
            throw ValidationException::withMessages(['action' => 'This request is approved in Lark and cannot be decided locally.']);
        }
        $provisionAi = false;

        $request = DB::transaction(function () use ($request, $actor, $action, $note, &$provisionAi): ServiceRequest {
            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($request->id);
            $approval = $request->approvals()
                ->where('round', $request->latestApprovalRound())
                ->where('status', 'pending')
                ->orderBy('step')
                ->first();

            if (! $approval || ! in_array($request->status, [ServiceRequestStatus::Submitted, ServiceRequestStatus::UnderReview], true)) {
                throw ValidationException::withMessages(['action' => 'This request has no review step waiting for action.']);
            }

            $approval->update([
                'approver_id' => $actor->id,
                'status' => $action === 'revision' ? 'revision_requested' : ($action === 'reject' ? 'rejected' : 'approved'),
                'note' => $note,
                'acted_at' => now(),
            ]);

            if ($action === 'revision') {
                $request->update(['status' => ServiceRequestStatus::RevisionRequested]);
                $message = 'Revision requested at '.$approval->stage.'.';
            } elseif ($action === 'reject') {
                $request->update(['status' => ServiceRequestStatus::Rejected, 'current_stage' => null]);
                $message = 'Request rejected at '.$approval->stage.'.';
            } else {
                $next = $request->approvals()
                    ->where('round', $approval->round)
                    ->where('step', '>', $approval->step)
                    ->where('status', 'pending')
                    ->orderBy('step')
                    ->first();

                if ($next) {
                    $request->update(['status' => ServiceRequestStatus::UnderReview, 'current_stage' => $next->stage]);
                    $message = $approval->stage.' approved. Next: '.$next->stage.'.';
                } else {
                    $request->update(['status' => ServiceRequestStatus::Approved, 'current_stage' => null, 'approved_at' => now()]);
                    $message = 'All approval stages completed.';
                    $provisionAi = $request->type === ServiceRequestType::AiToken
                        || ($request->type === ServiceRequestType::CustomSystem && (bool) data_get($request->details, 'needs_ai_analyzer'));
                }
            }

            $this->record($request, $actor, 'review', $message, ['action' => $action, 'note' => $note]);
            $this->notifyRequester($request, 'Request '.$request->status->label(), $message);

            return $request;
        });

        if ($provisionAi) {
            ProvisionAiCredential::dispatch($request)->afterCommit();
        }

        return $request;
    }

    public function resubmit(ServiceRequest $request, User $actor, array $data): ServiceRequest
    {
        if ($request->approval_source === 'lark') {
            throw ValidationException::withMessages(['request' => 'Revise and resubmit this request in Lark.']);
        }

        return DB::transaction(function () use ($request, $actor, $data): ServiceRequest {
            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->status !== ServiceRequestStatus::RevisionRequested) {
                throw ValidationException::withMessages(['request' => 'Only requests awaiting revision can be resubmitted.']);
            }

            $round = $request->latestApprovalRound() + 1;
            $request->update([
                ...$data,
                'status' => ServiceRequestStatus::Submitted,
                'current_stage' => $request->type->approvalStages()[0],
                'submitted_at' => now(),
            ]);
            $this->createApprovalRound($request, $round);
            $this->record($request, $actor, 'resubmitted', 'Revision submitted for review.', ['round' => $round]);
            $this->notifyReviewers($request, 'Revision ready '.$request->code, $request->title);

            return $request;
        });
    }

    public function transition(ServiceRequest $request, User $actor, string $status, ?int $assigneeId = null, ?string $note = null): ServiceRequest
    {
        return DB::transaction(function () use ($request, $actor, $status, $assigneeId, $note): ServiceRequest {
            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($request->id);
            $next = ServiceRequestStatus::from($status);
            $allowed = match ($request->status) {
                ServiceRequestStatus::Approved => [ServiceRequestStatus::InProgress],
                ServiceRequestStatus::InProgress => [ServiceRequestStatus::WaitingExternal, ServiceRequestStatus::Completed],
                ServiceRequestStatus::WaitingExternal => [ServiceRequestStatus::InProgress, ServiceRequestStatus::Completed],
                default => [],
            };

            if (! in_array($next, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'That status transition is not allowed.']);
            }
            if ($next === ServiceRequestStatus::InProgress && ! $assigneeId && ! $request->assigned_to) {
                throw ValidationException::withMessages(['assigned_to' => 'Assign an owner before starting delivery.']);
            }

            $values = ['status' => $next];
            if ($assigneeId !== null) {
                $values['assigned_to'] = $assigneeId;
            }
            if ($next === ServiceRequestStatus::InProgress && ! $request->started_at) {
                $values['started_at'] = now();
            }
            if ($next === ServiceRequestStatus::Completed) {
                $values['completed_at'] = now();
            }

            $request->update($values);
            $message = 'Delivery status changed to '.$next->label().($note ? ': '.$note : '.');
            $this->record($request, $actor, 'status', $message);
            $this->notifyRequester($request, 'Request '.$next->label(), $message);
            if ($assigneeId && $assigneeId !== $actor->id) {
                SystemNotification::send($assigneeId, 'Request assigned', $request->code.' is assigned to you.', 'info', 'mdi-account-check-outline', route('admin.requests.show', $request));
            }

            return $request;
        });
    }

    public function saveDelivery(ServiceRequest $request, User $actor, array $data): ServiceDelivery
    {
        return DB::transaction(function () use ($request, $actor, $data): ServiceDelivery {
            $delivery = $request->delivery()->updateOrCreate([], $data);
            $this->record($request, $actor, 'delivery', 'Delivery information updated.');
            $this->notifyRequester($request, 'Delivery updated', 'New delivery information is available for '.$request->code.'.');

            return $delivery;
        });
    }

    public function comment(ServiceRequest $request, User $actor, string $message): void
    {
        DB::transaction(function () use ($request, $actor, $message): void {
            $this->record($request, $actor, 'comment', $message);
            if ($actor->id === $request->requester_id) {
                $this->notifyReviewers($request, 'New comment '.$request->code, $message);
            } else {
                $this->notifyRequester($request, 'New comment '.$request->code, $message);
            }
        });
    }

    public function cancel(ServiceRequest $request, User $actor): void
    {
        if ($request->approval_source === 'lark') {
            throw ValidationException::withMessages(['request' => 'Cancel this request in Lark.']);
        }

        DB::transaction(function () use ($request, $actor): void {
            $request->update(['status' => ServiceRequestStatus::Cancelled, 'cancelled_at' => now(), 'current_stage' => null]);
            $this->record($request, $actor, 'cancelled', 'Request cancelled by requester.');
        });
    }

    private function createApprovalRound(ServiceRequest $request, int $round): void
    {
        $stages = $request->type->approvalStages();

        foreach ($stages as $index => $stage) {
            ServiceRequestApproval::create([
                'service_request_id' => $request->id,
                'round' => $round,
                'step' => $index + 1,
                'stage' => $stage,
            ]);
        }
    }

    private function record(ServiceRequest $request, ?User $actor, string $type, string $message, array $metadata = []): void
    {
        $request->updates()->create([
            'actor_id' => $actor?->id,
            'type' => $type,
            'message' => $message,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function notifyRequester(ServiceRequest $request, string $title, string $message): void
    {
        SystemNotification::send($request->requester_id, $title, $message, 'info', 'mdi-clipboard-text-clock-outline', route('v1.requests.show', $request));
    }

    private function notifyReviewers(ServiceRequest $request, string $title, string $message): void
    {
        User::permission('review service requests')->pluck('id')->each(
            fn (int $id) => SystemNotification::send($id, $title, $message, 'info', 'mdi-clipboard-check-outline', route('admin.requests.show', $request))
        );
    }
}
