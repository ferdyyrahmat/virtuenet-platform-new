<?php

namespace App\Http\Controllers\User;

use App\Enums\RequestApprovalStatus;
use App\Enums\RequestFulfilmentStatus;
use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\TicketNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserTicketController extends Controller
{
    protected TicketNotificationService $notifService;

    public function __construct(TicketNotificationService $notifService)
    {
        $this->notifService = $notifService;
    }

    public function index()
    {
        $user = Auth::user();
        $tickets = Ticket::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('v1.tickets.index', compact('tickets'));
    }

    public function show(string $code)
    {
        $ticket = Ticket::with(['replies' => function ($q) {
            $q->where('is_internal_note', false)->orderBy('created_at', 'asc');
        }, 'assignedDeveloper'])->where('ticket_code', $code)->firstOrFail();
        $this->authorize('view', $ticket);

        return view('v1.tickets.show', compact('ticket'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'application_reference' => 'required|string|max:160',
            'category' => 'required|in:bug,feature_request,general_inquiry,server_issue,billing',
            'severity' => 'required|in:low,medium,high,critical',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'description' => 'required|string|max:10000',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $user = Auth::user();
        $ticketCode = Ticket::generateTicketCode();

        $ticket = DB::transaction(function () use ($request, $user, $ticketCode): Ticket {
            $serviceRequest = ServiceRequest::create([
                'code' => $ticketCode,
                'requester_id' => $user->id,
                'department_id' => $user->departments()->wherePivot('is_primary', true)->value('departments.id') ?? $user->departments()->value('departments.id'),
                'type' => 'support',
                'schema_version' => 1,
                'title' => $request->subject,
                'description' => $request->description,
                'status' => ServiceRequestStatus::Submitted,
                'approval_status' => RequestApprovalStatus::Approved,
                'fulfilment_status' => RequestFulfilmentStatus::NotStarted,
                'priority' => ($request->priority ?? 'medium') === 'medium' ? 'normal' : $request->priority,
                'details' => ['application' => $request->application_reference, 'severity' => $request->severity, 'category' => $request->category],
                'source' => 'platform',
                'approval_source' => 'none',
                'approval_sync_status' => 'synced',
                'submitted_at' => now(),
            ]);
            $serviceRequest->updates()->create(['actor_id' => $user->id, 'type' => 'submitted', 'message' => 'Support request submitted for triage.']);

            return Ticket::create([
                'service_request_id' => $serviceRequest->id,
                'ticket_code' => $ticketCode,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'subject' => $request->subject,
                'application_reference' => $request->application_reference,
                'category' => $request->category,
                'severity' => $request->severity,
                'priority' => $request->priority ?? 'medium',
                'status' => 'open',
                'description' => $request->description,
            ]);
        });

        audit_log("Submitted support ticket #{$ticketCode}", 'create', 'ticket');
        $this->notifService->notifyTicketCreated($ticket);

        $msg = "Ticket #{$ticketCode} created successfully! You can track progress here.";

        return response()->json([
            'success' => true,
            'message' => $msg,
            'ticket_code' => $ticketCode,
            'redirect' => route('v1.tickets.show', $ticketCode),
        ]);
    }

    public function reply(Request $request, string $code)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $ticket = Ticket::where('ticket_code', $code)->firstOrFail();
        $user = Auth::user();
        $this->authorize('reply', $ticket);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user?->id,
            'sender_type' => 'user',
            'sender_name' => $user ? $user->name : $ticket->name,
            'sender_email' => $user ? $user->email : $ticket->email,
            'message' => $request->message,
            'is_internal_note' => false,
        ]);

        // Re-open ticket if it was resolved/closed
        if (in_array($ticket->status, ['resolved', 'closed', 'waiting_user'])) {
            $ticket->status = 'in_progress';
            $ticket->save();
            $ticket->serviceRequest?->update(['status' => ServiceRequestStatus::InProgress, 'fulfilment_status' => RequestFulfilmentStatus::Provisioning]);
        }

        audit_log("Replied on ticket #{$code}", 'create', 'ticket');
        $this->notifService->notifyTicketReplied($ticket, $reply);

        return response()->json([
            'success' => true,
            'message' => 'Your reply has been submitted successfully.',
            'redirect' => route('v1.tickets.show', $code),
        ]);
    }
}
