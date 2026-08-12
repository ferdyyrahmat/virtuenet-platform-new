<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewServiceRequestRequest;
use App\Jobs\ProvisionAiCredential;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ServiceRequestWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $departmentIds = $request->user()->departments()->pluck('departments.id');
        $requests = ServiceRequest::query()
            ->with(['requester', 'assignee'])
            ->unless($request->user()->isDeveloper(), fn ($query) => $query->where(function ($query) use ($request, $departmentIds) {
                $query->whereNull('department_id')
                    ->orWhereIn('department_id', $departmentIds)
                    ->orWhere('requester_id', $request->user()->id)
                    ->orWhere('assigned_to', $request->user()->id);
            }))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('approval_status'), fn ($query) => $query->where('approval_status', $request->string('approval_status')))
            ->when($request->filled('fulfilment_status'), fn ($query) => $query->where('fulfilment_status', $request->string('fulfilment_status')))
            ->when($request->string('view')->toString() === 'active', fn ($query) => $query->whereIn('status', [ServiceRequestStatus::Submitted, ServiceRequestStatus::UnderReview, ServiceRequestStatus::Approved, ServiceRequestStatus::InProgress, ServiceRequestStatus::WaitingExternal]))
            ->when($request->string('view')->toString() === 'review', fn ($query) => $query->whereIn('approval_status', ['submitted', 'in_approval']))
            ->when($request->string('sync')->toString() === 'drift', fn ($query) => $query->where('approval_source', 'lark')->where('approval_sync_status', '!=', 'synced'))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('requester_id'), fn ($query) => $query->where('requester_id', $request->integer('requester_id')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($nested) => $nested->where('code', 'like', $term)->orWhere('title', 'like', $term));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = ServiceRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.requests.index', compact('requests', 'stats'));
    }

    public function show(ServiceRequest $serviceRequest): View
    {
        $this->authorize('view', $serviceRequest);
        $serviceRequest->load(['requester', 'department', 'parent', 'template', 'assignee', 'approvals.approver', 'updates.actor', 'delivery', 'aiCredential', 'subscription.currentVersion', 'subscription.evidences', 'financialEntries', 'attachments']);
        $operators = User::permission('manage service requests')->orderBy('name')->get();

        return view('admin.requests.show', compact('serviceRequest', 'operators'));
    }

    public function review(ReviewServiceRequestRequest $request, ServiceRequest $serviceRequest, ServiceRequestWorkflow $workflow): JsonResponse
    {
        $this->authorize('review', $serviceRequest);
        $workflow->review($serviceRequest, $request->user(), $request->string('action'), $request->input('note'));

        return $this->success('Review recorded.', $serviceRequest);
    }

    public function transition(Request $request, ServiceRequest $serviceRequest, ServiceRequestWorkflow $workflow): JsonResponse
    {
        $this->authorize('manage', $serviceRequest);
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ServiceRequestStatus::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);
        $workflow->transition($serviceRequest, $request->user(), $validated['status'], $validated['assigned_to'] ?? null, $validated['note'] ?? null);

        return $this->success('Delivery status updated.', $serviceRequest);
    }

    public function delivery(Request $request, ServiceRequest $serviceRequest, ServiceRequestWorkflow $workflow): JsonResponse
    {
        $this->authorize('manage', $serviceRequest);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'active', 'delivered', 'expired'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'access_url' => ['nullable', 'url:http,https', 'max:2048'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
        $workflow->saveDelivery($serviceRequest, $request->user(), $validated);

        return $this->success('Delivery information saved.', $serviceRequest);
    }

    public function provision(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorize('manage', $serviceRequest);
        abort_unless($serviceRequest->type === ServiceRequestType::AiToken || data_get($serviceRequest->details, 'needs_ai_analyzer'), 422);
        abort_unless(in_array($serviceRequest->status, [ServiceRequestStatus::Approved, ServiceRequestStatus::InProgress, ServiceRequestStatus::WaitingExternal, ServiceRequestStatus::Completed], true), 422);
        abort_if($serviceRequest->aiCredential()->exists(), 422, 'An AI credential already exists for this request.');
        ProvisionAiCredential::dispatch($serviceRequest);

        return $this->success('AI provisioning queued.', $serviceRequest);
    }

    private function success(string $message, ServiceRequest $request): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'redirect' => route('admin.requests.show', $request)]);
    }
}
