<?php

namespace App\Http\Controllers\System\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\TicketNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    protected TicketNotificationService $notifService;

    public function __construct(TicketNotificationService $notifService)
    {
        $this->notifService = $notifService;
    }

    public function index(Request $request)
    {
        $query = Ticket::with(['user', 'assignedDeveloper'])->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $tickets = $query->get();
        $developers = Developer::where('is_active', true)->get();

        $stats = [
            'total'       => Ticket::count(),
            'open'        => Ticket::where('status', 'open')->count(),
            'in_progress' => Ticket::where('status', 'in_progress')->count(),
            'resolved'    => Ticket::where('status', 'resolved')->count(),
        ];

        return view('admin.tickets.index', compact('tickets', 'developers', 'stats'));
    }

    public function show(string $id)
    {
        $ticket = Ticket::with(['user', 'assignedDeveloper', 'replies.user'])->findOrFail($id);
        $developers = Developer::where('is_active', true)->get();

        return view('admin.tickets.show', compact('ticket', 'developers'));
    }

    public function reply(Request $request, string $id)
    {
        $request->validate([
            'message'          => 'required|string',
            'is_internal_note' => 'nullable|boolean',
            'status'           => 'nullable|in:open,in_progress,waiting_user,resolved,closed',
        ]);

        $ticket = Ticket::findOrFail($id);
        $user = Auth::user();

        $reply = TicketReply::create([
            'ticket_id'        => $ticket->id,
            'user_id'          => $user->id,
            'sender_type'      => $user->ticketSenderType(),
            'sender_name'      => $user->name,
            'sender_email'     => $user->email,
            'message'          => $request->message,
            'is_internal_note' => $request->boolean('is_internal_note'),
        ]);

        if ($request->filled('status')) {
            $oldStatus = $ticket->status;
            $ticket->status = $request->status;
            if ($request->status === 'resolved' && !$ticket->resolved_at) {
                $ticket->resolved_at = now();
            }
            $ticket->save();
        }

        audit_log("Posted reply on ticket #{$ticket->ticket_code}", 'create', 'ticket');
        $this->notifService->notifyTicketReplied($ticket, $reply);

        return response()->json([
            'success'  => true,
            'message'  => 'Reply posted successfully!',
            'redirect' => route('admin.tickets.show', $ticket->id),
            'reply'    => $reply
        ]);
    }

    public function assign(Request $request, string $id)
    {
        $request->validate([
            'assigned_developer_id' => 'nullable|exists:developers,id',
            'status'                => 'nullable|in:open,in_progress,waiting_user,resolved,closed',
        ]);

        $ticket = Ticket::findOrFail($id);
        $ticket->assigned_developer_id = $request->assigned_developer_id;

        if ($request->filled('status')) {
            $ticket->status = $request->status;
        }

        $ticket->save();

        $devName = $ticket->assignedDeveloper ? $ticket->assignedDeveloper->name : 'Unassigned';
        audit_log("Assigned ticket #{$ticket->ticket_code} to {$devName}", 'update', 'ticket');

        return response()->json([
            'success'  => true,
            'message'  => "Ticket assigned to {$devName} successfully!",
            'redirect' => route('admin.tickets.show', $ticket->id)
        ]);
    }

    public function destroy(string $id)
    {
        $ticket = Ticket::findOrFail($id);
        $code = $ticket->ticket_code;
        $ticket->delete();

        audit_log("Deleted ticket #{$code}", 'delete', 'ticket');

        return response()->json([
            'success'  => true,
            'message'  => "Ticket #{$code} deleted successfully.",
            'redirect' => route('admin.tickets.index')
        ]);
    }
}
