<?php

namespace App\Services;

use App\Models\Developer;
use App\Models\SystemNotification;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Support\Str;

class TicketNotificationService
{
    public function notifyTicketCreated(Ticket $ticket): void
    {
        $developers = $ticket->assignedDeveloper
            ? collect([$ticket->assignedDeveloper])
            : Developer::where('is_active', true)->get();

        foreach ($developers as $developer) {
            if ($developer->user) {
                SystemNotification::send(
                    $developer->user,
                    "New Support Ticket #{$ticket->ticket_code}",
                    "{$ticket->subject} (Priority: " . strtoupper($ticket->priority) . ')',
                    'ticket',
                    'mdi-ticket-account',
                    route('admin.tickets.show', $ticket->id),
                );
            }
        }

        if ($ticket->user) {
            SystemNotification::send(
                $ticket->user,
                "Ticket Submitted #{$ticket->ticket_code}",
                "Your ticket '{$ticket->subject}' has been submitted.",
                'ticket',
                'mdi-ticket-confirmation-outline',
                route('v1.tickets.show', $ticket->ticket_code),
            );
        }
    }

    public function notifyTicketReplied(Ticket $ticket, TicketReply $reply): void
    {
        if ($reply->is_internal_note) {
            return;
        }

        if (in_array($reply->sender_type, ['developer', 'admin'], true)) {
            if ($ticket->user) {
                SystemNotification::send(
                    $ticket->user,
                    "New Reply on Ticket #{$ticket->ticket_code}",
                    "{$reply->sender_name}: " . Str::limit($reply->message, 80),
                    'ticket',
                    'mdi-reply-all-outline',
                    route('v1.tickets.show', $ticket->ticket_code),
                );
            }

            return;
        }

        $developers = $ticket->assignedDeveloper
            ? collect([$ticket->assignedDeveloper])
            : Developer::where('is_active', true)->get();

        foreach ($developers as $developer) {
            if ($developer->user) {
                SystemNotification::send(
                    $developer->user,
                    "User Replied on Ticket #{$ticket->ticket_code}",
                    "{$ticket->name}: " . Str::limit($reply->message, 80),
                    'ticket',
                    'mdi-comment-account-outline',
                    route('admin.tickets.show', $ticket->id),
                );
            }
        }
    }
}
