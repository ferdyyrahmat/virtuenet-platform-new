<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\TicketNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $user = Auth::user();
        $ticket = Ticket::with(['replies' => function ($q) {
            $q->where('is_internal_note', false)->orderBy('created_at', 'asc');
        }, 'assignedDeveloper'])->where('ticket_code', $code)->whereBelongsTo($user)->firstOrFail();

        return view('v1.tickets.show', compact('ticket'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject'     => 'required|string|max:255',
            'category'    => 'required|in:bug,feature_request,general_inquiry,server_issue,billing',
            'priority'    => 'nullable|in:low,medium,high,urgent',
            'description' => 'required|string',
            'name'        => 'nullable|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
        ]);

        $user = Auth::user();
        $ticketCode = Ticket::generateTicketCode();

        $ticket = Ticket::create([
            'ticket_code' => $ticketCode,
            'user_id'     => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'subject'     => $request->subject,
            'category'    => $request->category,
            'priority'    => $request->priority ?? 'medium',
            'status'      => 'open',
            'description' => $request->description,
        ]);

        audit_log("Submitted support ticket #{$ticketCode}", 'create', 'ticket');
        $this->notifService->notifyTicketCreated($ticket);

        $msg = "Ticket #{$ticketCode} created successfully! You can track progress here.";

        return response()->json([
            'success'     => true,
            'message'     => $msg,
            'ticket_code' => $ticketCode,
            'redirect'    => route('v1.tickets.show', $ticketCode)
        ]);
    }

    public function reply(Request $request, string $code)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $user = Auth::user();
        $ticket = Ticket::where('ticket_code', $code)->whereBelongsTo($user)->firstOrFail();

        $reply = TicketReply::create([
            'ticket_id'        => $ticket->id,
            'user_id'          => $user->id,
            'sender_type'      => $user->ticketSenderType(),
            'sender_name'      => $user ? $user->name : $ticket->name,
            'sender_email'     => $user ? $user->email : $ticket->email,
            'message'          => $request->message,
            'is_internal_note' => false,
        ]);

        // Re-open ticket if it was resolved/closed
        if (in_array($ticket->status, ['resolved', 'closed', 'waiting_user'])) {
            $ticket->status = 'in_progress';
            $ticket->save();
        }

        audit_log("Replied on ticket #{$code}", 'create', 'ticket');
        $this->notifService->notifyTicketReplied($ticket, $reply);

        return response()->json([
            'success'  => true,
            'message'  => 'Your reply has been submitted successfully.',
            'redirect' => route('v1.tickets.show', $code)
        ]);
    }
}
