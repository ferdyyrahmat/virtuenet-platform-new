<?php

namespace App\Http\Controllers\User;

use App\Enums\ServiceRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequestRequest;
use App\Models\RequestTemplate;
use App\Models\ServiceRequest;
use App\Services\ServiceRequestAttachmentService;
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
            ->when($request->filled('approval_status'), fn ($query) => $query->where('approval_status', $request->string('approval_status')))
            ->when($request->filled('fulfilment_status'), fn ($query) => $query->where('fulfilment_status', $request->string('fulfilment_status')))
            ->when($request->string('view')->toString() === 'active', fn ($query) => $query->whereIn('status', ['submitted', 'under_review', 'approved', 'in_progress', 'waiting_external']))
            ->when($request->string('view')->toString() === 'review', fn ($query) => $query->whereIn('approval_status', ['submitted', 'in_approval']))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('requests.index', compact('requests'));
    }

    public function create(Request $request): View
    {
        $type = ServiceRequestType::tryFrom((string) $request->query('type'));
        $selectedTemplate = RequestTemplate::query()->where('active', true)->find($request->integer('template'));
        if ($selectedTemplate) {
            $type = $selectedTemplate->request_type;
        }
        $templates = RequestTemplate::query()->where('active', true)->whereNotNull('approved_at')->orderBy('request_type')->orderBy('name')->get();
        $departments = $request->user()->departments()->where('active', true)->orderBy('name')->get();
        $projects = $request->user()->serviceRequests()->where('type', ServiceRequestType::CustomSystem)->latest()->limit(20)->get();
        $larkManaged = (bool) config('services.lark.approval_enabled') && ! (bool) config('services.lark.approval_outbound_enabled');
        $larkFormUrl = config('services.lark.approval_form_url');

        return view('requests.create', compact('type', 'selectedTemplate', 'templates', 'departments', 'projects', 'larkManaged', 'larkFormUrl'));
    }

    public function store(StoreServiceRequestRequest $request, ServiceRequestWorkflow $workflow, ServiceRequestAttachmentService $attachments): JsonResponse
    {
        $serviceRequest = $workflow->create($request->user(), $request->validated());
        $attachments->store($serviceRequest, $request->user(), $request->file('attachments', []));

        return response()->json([
            'success' => true,
            'message' => 'Request '.$serviceRequest->code.' submitted successfully.',
            'redirect' => route('v1.requests.show', $serviceRequest),
        ]);
    }

    public function show(ServiceRequest $serviceRequest): View
    {
        $this->authorize('view', $serviceRequest);
        $serviceRequest->load(['requester', 'department', 'parent', 'template', 'assignee', 'approvals.approver', 'updates.actor', 'delivery', 'aiCredential', 'subscription.currentVersion', 'subscription.evidences', 'financialEntries', 'attachments']);

        return view('requests.show', compact('serviceRequest'));
    }

    public function resubmit(StoreServiceRequestRequest $request, ServiceRequest $serviceRequest, ServiceRequestWorkflow $workflow, ServiceRequestAttachmentService $attachments): JsonResponse
    {
        $this->authorize('update', $serviceRequest);
        $data = $request->validated();
        $data['type'] = $serviceRequest->type->value;
        $workflow->resubmit($serviceRequest, $request->user(), $data);
        $attachments->store($serviceRequest, $request->user(), $request->file('attachments', []));

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
