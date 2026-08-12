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
        $requests = ServiceRequest::query()
            ->with(['requester', 'assignee'])
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
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
        $serviceRequest->load(['requester', 'assignee', 'approvals.approver', 'updates.actor', 'delivery', 'aiCredential']);
        $operators = User::permission('manage service requests')->orderBy('name')->get();

        return view('admin.requests.show', compact('serviceRequest', 'operators'));
    }

    public function review(ReviewServiceRequestRequest $request, ServiceRequest $serviceRequest, ServiceRequestWorkflow $workflow): JsonResponse
    {
        $workflow->review($serviceRequest, $request->user(), $request->string('action'), $request->input('note'));

        return $this->success('Review recorded.', $serviceRequest);
    }

    public function transition(Request $request, ServiceRequest $serviceRequest, ServiceRequestWorkflow $workflow): JsonResponse
    {
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

    public function provision(ServiceRequest $serviceRequest): JsonResponse
    {
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
