<?php

namespace App\Http\Controllers\User;

use App\Enums\ServiceRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequestRequest;
use App\Models\ServiceRequest;
use App\Services\ServiceRequestWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = $request->user()->serviceRequests()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('requests.index', compact('requests'));
    }

    public function create(Request $request): View
    {
        $type = ServiceRequestType::tryFrom((string) $request->query('type'));

        return view('requests.create', compact('type'));
    }

    public function store(StoreServiceRequestRequest $request, ServiceRequestWorkflow $workflow): JsonResponse
    {
        $serviceRequest = $workflow->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Request '.$serviceRequest->code.' submitted successfully.',
            'redirect' => route('v1.requests.show', $serviceRequest),
        ]);
    }

    public function show(ServiceRequest $serviceRequest): View
    {
        $this->authorize('view', $serviceRequest);
        $serviceRequest->load(['requester', 'assignee', 'approvals.approver', 'updates.actor', 'delivery', 'aiCredential']);

        return view('requests.show', compact('serviceRequest'));
    }

    public function resubmit(StoreServiceRequestRequest $request, ServiceRequest $serviceRequest, ServiceRequestWorkflow $workflow): JsonResponse
    {
        $this->authorize('update', $serviceRequest);
        $data = $request->validated();
        $data['type'] = $serviceRequest->type->value;
        $workflow->resubmit($serviceRequest, $request->user(), $data);

        return response()->json(['success' => true, 'message' => 'Revision submitted.', 'redirect' => route('v1.requests.show', $serviceRequest)]);
    }

    public function comment(Request $request, ServiceRequest $serviceRequest, ServiceRequestWorkflow $workflow): JsonResponse
    {
        $this->authorize('view', $serviceRequest);
        $validated = $request->validate(['message' => ['required', 'string', 'max:5000']]);
        $workflow->comment($serviceRequest, $request->user(), $validated['message']);

        return response()->json(['success' => true, 'message' => 'Comment added.', 'redirect' => route('v1.requests.show', $serviceRequest)]);
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest, ServiceRequestWorkflow $workflow): JsonResponse
    {
        $this->authorize('cancel', $serviceRequest);
        $workflow->cancel($serviceRequest, $request->user());

        return response()->json(['success' => true, 'message' => 'Request cancelled.', 'redirect' => route('v1.requests.index')]);
    }
}
