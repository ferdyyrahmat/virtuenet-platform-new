@extends('layouts.vertical', ['title' => 'Ticket #' . $ticket->ticket_code])

@section('content')
<div class="container-fluid">
    <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
        <div class="flex-grow-1">
            <h4 class="fs-18 fw-semibold m-0">Support Ticket #{{ $ticket->ticket_code }}</h4>
        </div>
        <div class="text-end">
            <a href="{{ route('v1.tickets.index') }}" class="btn btn-outline-secondary btn-sm me-2">
                <i class="mdi mdi-arrow-left me-1"></i>Back to My Tickets
            </a>
            <ol class="breadcrumb m-0 py-0 d-inline-flex align-items-center">
                <li class="breadcrumb-item"><a href="{{ route('v1.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('v1.tickets.index') }}">Tickets</a></li>
                <li class="breadcrumb-item active">#{{ $ticket->ticket_code }}</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <!-- Main Chat Discussion Thread -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-3">
                    <div>
                        <span class="badge bg-primary-subtle text-primary font-monospace text-uppercase me-1">
                            {{ str_replace('_', ' ', $ticket->category) }}
                        </span>
                        <h5 class="card-title mb-0 d-inline-block fw-bold text-dark">{{ $ticket->subject }}</h5>
                    </div>
                    <span id="ticket-status-badge" class="badge bg-{{ match($ticket->status) { 'resolved' => 'success', 'in_progress' => 'primary', 'waiting_user' => 'info', default => 'warning' } }}-subtle text-uppercase fw-bold fs-12">
                        <i class="mdi {{ $ticket->status == 'resolved' ? 'mdi-check-circle' : 'mdi-clock-outline' }} me-1"></i>{{ str_replace('_', ' ', $ticket->status) }}
                    </span>
                </div>

                <div class="card-body p-4">
                    <!-- Ticket Initial Issue Description -->
                    <div class="d-flex align-items-start mb-4 p-3 bg-light rounded-3 border">
                        <div class="avatar-sm me-3">
                            <span class="avatar-title bg-primary text-white rounded-circle fw-bold fs-16">
                                {{ strtoupper(substr($ticket->name, 0, 1)) }}
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <h6 class="fw-bold text-dark mb-0 fs-14">{{ $ticket->name }} <small class="text-muted fw-normal">(You)</small></h6>
                                <small class="text-muted fs-12"><i class="mdi mdi-clock-outline me-1"></i>{{ $ticket->created_at->format('Y-m-d H:i') }}</small>
                            </div>
                            <p class="text-dark mb-0 fs-13" style="white-space: pre-wrap;">{{ $ticket->description }}</p>
                        </div>
                    </div>

                    <hr class="my-4 text-muted opacity-25">

                    <h6 class="fw-bold text-muted text-uppercase fs-12 mb-3">
                        <i class="mdi mdi-forum-outline me-1"></i>Discussion Thread (<span id="reply-count">{{ $ticket->replies->count() }}</span>)
                    </h6>

                    <!-- Replies Discussion List -->
                    <div class="ticket-thread-list" id="ticket-thread-list" style="max-height: 480px; overflow-y: auto;">
                        @forelse($ticket->replies as $reply)
                            @php $isDev = in_array($reply->sender_type, ['developer', 'admin']); @endphp
                            <div class="reply-bubble d-flex align-items-start mb-3 p-3 rounded-3 border {{ $isDev ? 'bg-primary-subtle border-primary-subtle' : 'bg-white' }}" data-reply-id="{{ $reply->id }}">
                                <div class="avatar-sm me-3">
                                    <span class="avatar-title bg-{{ $isDev ? 'primary' : 'secondary' }} text-white rounded-circle fw-bold fs-14">
                                        {{ strtoupper(substr($reply->sender_name, 0, 1)) }}
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div>
                                            <h6 class="fw-bold mb-0 fs-13 d-inline-block text-dark">{{ $reply->sender_name }}</h6>
                                            @if($isDev)
                                                <span class="badge bg-primary text-white font-monospace text-uppercase ms-1 fs-10">👨‍💻 Support Developer</span>
                                            @else
                                                <span class="badge bg-secondary text-white font-monospace text-uppercase ms-1 fs-10">You</span>
                                            @endif
                                        </div>
                                        <small class="text-muted fs-12"><i class="mdi mdi-clock-outline me-1"></i>{{ $reply->created_at->format('Y-m-d H:i') }}</small>
                                    </div>
                                    <p class="text-dark mb-0 fs-13" style="white-space: pre-wrap;">{{ $reply->message }}</p>
                                </div>
                            </div>
                        @empty
                            <div id="no-replies-placeholder" class="text-center py-4 text-muted">
                                <i class="mdi mdi-message-outline fs-32 text-muted d-block mb-1"></i>
                                <p class="mb-0 fs-13">No replies yet. Our support engineering team will reply shortly.</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- User Post Reply Form -->
                    <div class="mt-4 pt-3 border-top">
                        <form id="form-user-ticket-reply" action="{{ route('v1.tickets.reply', $ticket->ticket_code) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <textarea class="form-control" name="message" id="user-reply-input" rows="3" required placeholder="Type your reply or additional information..."></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-sm fw-bold px-4" id="btn-submit-user-reply">
                                    <i class="mdi mdi-send me-1"></i>Send Reply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Info Panel -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-body-tertiary py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark"><i class="mdi mdi-information-outline text-primary me-1"></i>Ticket Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless fs-13 mb-0">
                        <tr>
                            <td class="text-muted ps-0">Tracking Code:</td>
                            <td class="fw-bold font-monospace text-dark text-end pe-0">#{{ $ticket->ticket_code }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0">Category:</td>
                            <td class="text-dark text-end pe-0 text-capitalize">{{ str_replace('_', ' ', $ticket->category) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0">Priority:</td>
                            <td class="text-end pe-0">
                                <span class="badge bg-{{ match($ticket->priority) { 'urgent' => 'danger', 'high' => 'warning', default => 'info' } }} text-white">
                                    {{ strtoupper($ticket->priority) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0">Assigned Developer:</td>
                            <td class="text-dark text-end pe-0">
                                {{ $ticket->assignedDeveloper ? $ticket->assignedDeveloper->name : 'Support Engineering Team' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0">Created At:</td>
                            <td class="text-dark text-end pe-0">{{ $ticket->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script-bottom')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ticketCode = "{{ $ticket->ticket_code }}";

        function appendReplyBubble(data) {
            if (data.is_internal_note) return; // Users do not see internal notes
            if ($(`.reply-bubble[data-reply-id="${data.id}"]`).length) return;

            $('#no-replies-placeholder').remove();

            const isDev = ['developer', 'admin'].includes(data.sender_type);
            const firstLetter = (data.sender_name || 'U').substring(0, 1).toUpperCase();

            const bubbleHtml = `
                <div class="reply-bubble d-flex align-items-start mb-3 p-3 rounded-3 border ${isDev ? 'bg-primary-subtle border-primary-subtle' : 'bg-white'}" data-reply-id="${data.id}">
                    <div class="avatar-sm me-3">
                        <span class="avatar-title bg-${isDev ? 'primary' : 'secondary'} text-white rounded-circle fw-bold fs-14">
                            ${firstLetter}
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div>
                                <h6 class="fw-bold mb-0 fs-13 d-inline-block text-dark">${data.sender_name}</h6>
                                ${isDev ? '<span class="badge bg-primary text-white font-monospace text-uppercase ms-1 fs-10">👨‍💻 Support Developer</span>' : '<span class="badge bg-secondary text-white font-monospace text-uppercase ms-1 fs-10">You</span>'}
                            </div>
                            <small class="text-muted fs-12"><i class="mdi mdi-clock-outline me-1"></i>${data.created_at || 'Just now'}</small>
                        </div>
                        <p class="text-dark mb-0 fs-13" style="white-space: pre-wrap;">${data.message}</p>
                    </div>
                </div>
            `;

            $('#ticket-thread-list').append(bubbleHtml);

            // Update reply counter
            const counter = document.getElementById('reply-count');
            if (counter) counter.textContent = parseInt(counter.textContent || 0) + 1;

            // Update status badge
            const statusBadge = document.getElementById('ticket-status-badge');
            if (statusBadge) {
                statusBadge.className = 'badge bg-primary-subtle text-uppercase fw-bold fs-12';
                statusBadge.innerHTML = '<i class="mdi mdi-clock-outline me-1"></i>IN PROGRESS';
            }

            // Scroll smoothly to newest message
            const threadList = document.getElementById('ticket-thread-list');
            threadList.scrollTop = threadList.scrollHeight;
        }

        // Send Reply Form Submit Handler (ZERO ALERTS / TOASTS!)
        const formReply = document.getElementById('form-user-ticket-reply');
        if (formReply) {
            formReply.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();

                const messageInput = document.getElementById('user-reply-input');
                const messageText = messageInput.value.trim();

                if (!messageText) return;

                const btn = document.getElementById('btn-submit-user-reply');
                btn.disabled = true;

                $.ajax({
                    url: this.action,
                    type: 'POST',
                    data: $(this).serialize(),
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(resp) {
                        btn.disabled = false;
                        messageInput.value = '';
                    },
                    error: function(xhr) {
                        btn.disabled = false;
                        alert('Failed to send reply: ' + (xhr.responseJSON?.message || 'Server error'));
                    }
                });
            }, true);
        }
    });
</script>
@endsection
