<?php

namespace App\Http\Controllers\System\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\TicketNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class TicketController extends Controller
{
    protected TicketNotificationService $notifService;

    public function __construct(TicketNotificationService $notifService)
    {
        $this->notifService = $notifService;
    }

    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $query = Ticket::query()->with(['user', 'assignedDeveloper']);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }

            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            return DataTables::of($query->orderBy('created_at', 'desc'))
                ->editColumn('ticket_code', function ($ticket) {
                    return '<a href="' . route('admin.tickets.show', $ticket->id) . '" class="fw-bold font-monospace text-primary">#' . e($ticket->ticket_code) . '</a>';
                })
                ->addColumn('user', function ($ticket) {
                    return '<div class="fw-semibold text-dark">' . e($ticket->name) . '</div><small class="text-muted">' . e($ticket->email) . '</small>';
                })
                ->addColumn('subject_category', function ($ticket) {
                    $catClass = match ($ticket->category) {
                        'bug' => 'danger',
                        'server_issue' => 'warning',
                        'feature_request' => 'info',
                        default => 'secondary',
                    };

                    return '<span class="badge bg-' . $catClass . '-subtle text-' . $catClass . ' font-monospace text-uppercase me-1">' . str_replace('_', ' ', $ticket->category) . '</span><span class="fw-semibold text-dark">' . e($ticket->subject) . '</span>';
                })
                ->editColumn('priority', function ($ticket) {
                    $prioClass = match ($ticket->priority) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'secondary',
                    };

                    return '<span class="badge bg-' . $prioClass . ' text-white text-uppercase fs-11">' . $ticket->priority . '</span>';
                })
                ->addColumn('assigned_dev', function ($ticket) {
                    if ($ticket->assignedDeveloper) {
                        return '<span class="badge bg-info-subtle text-info fw-semibold"><i class="mdi mdi-account-code me-1"></i>' . e($ticket->assignedDeveloper->name) . '</span>';
                    }

                    return '<span class="badge bg-secondary-subtle text-muted fs-11">' . e(__('messages.unassigned')) . '</span>';
                })
                ->editColumn('status', function ($ticket) {
                    $stClass = match ($ticket->status) {
                        'resolved' => 'success',
                        'in_progress' => 'primary',
                        'waiting_user' => 'info',
                        'closed' => 'secondary',
                        default => 'danger',
                    };

                    return '<span class="badge bg-' . $stClass . '-subtle text-' . $stClass . ' fw-bold text-uppercase">' . str_replace('_', ' ', $ticket->status) . '</span>';
                })
                ->editColumn('created_at', function ($ticket) {
                    return $ticket->created_at ? $ticket->created_at->format('Y-m-d H:i') : '-';
                })
                ->addColumn('actions', function ($ticket) {
                    return '<div class="text-end"><a href="' . route('admin.tickets.show', $ticket->id) . '" class="btn btn-outline-primary btn-xs me-1"><i class="mdi mdi-eye-outline me-1"></i>' . e(__('messages.view_thread')) . '</a><button type="button" class="btn btn-outline-danger btn-xs" onclick="deleteTicket(' . $ticket->id . ', \'' . $ticket->ticket_code . '\')"><i class="mdi mdi-trash-can-outline"></i></button></div>';
                })
                ->rawColumns(['ticket_code', 'user', 'subject_category', 'priority', 'assigned_dev', 'status', 'actions'])
                ->make(true);
        }

        $stats = [
            'total'       => Ticket::count(),
            'open'        => Ticket::where('status', 'open')->count(),
            'in_progress' => Ticket::where('status', 'in_progress')->count(),
            'resolved'    => Ticket::where('status', 'resolved')->count(),
        ];

        return view('admin.tickets.index', compact('stats'));
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
