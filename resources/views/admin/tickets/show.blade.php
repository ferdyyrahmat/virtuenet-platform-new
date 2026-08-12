@extends('layouts.vertical', ['title' => 'Ticket #' . $ticket->ticket_code])

@section('content')
<div class="container-fluid">
    <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
        <div class="flex-grow-1">
            <h4 class="fs-18 fw-semibold m-0">Ticket #{{ $ticket->ticket_code }}</h4>
        </div>
        <div class="text-end">
            <a href="{{ route('admin.tickets.index') }}" class="btn btn-outline-secondary btn-sm me-2">
                <i class="mdi mdi-arrow-left me-1"></i>Back to Tickets
            </a>
            <ol class="breadcrumb m-0 py-0 d-inline-flex align-items-center">
                <li class="breadcrumb-item"><a href="{{ route('root') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.tickets.index') }}">Tickets</a></li>
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
                    <span id="ticket-status-badge" class="badge bg-{{ match($ticket->status) { 'resolved' => 'success', 'in_progress' => 'primary', 'waiting_user' => 'info', default => 'danger' } }}-subtle text-uppercase fw-bold fs-12">
                        {{ str_replace('_', ' ', $ticket->status) }}
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
                                <h6 class="fw-bold text-dark mb-0 fs-14">{{ $ticket->name }} <small class="text-muted fw-normal">({{ $ticket->email }})</small></h6>
                                <small class="text-muted fs-12"><i class="mdi mdi-clock-outline me-1"></i>{{ $ticket->created_at->format('Y-m-d H:i') }}</small>
                            </div>
                            <p class="text-dark mb-0 fs-13" style="white-space: pre-wrap;">{{ $ticket->description }}</p>
                        </div>
                    </div>

                    <hr class="my-4 text-muted opacity-25">

                    <h6 class="fw-bold text-muted text-uppercase fs-12 mb-3">
                        <i class="mdi mdi-forum-outline me-1"></i>Discussion Thread (<span id="reply-count">{{ $ticket->replies->count() }}</span> Replies)
                    </h6>

                    <!-- Replies Discussion List -->
                    <div class="ticket-thread-list" id="ticket-thread-list" style="max-height: 480px; overflow-y: auto;">
                        @forelse($ticket->replies as $reply)
                            @php
                                $isDev = in_array($reply->sender_type, ['developer', 'admin']);
                                $isInternal = $reply->is_internal_note;
                            @endphp
                            <div class="reply-bubble d-flex align-items-start mb-3 p-3 rounded-3 border {{ $isInternal ? 'bg-warning-subtle border-warning' : ($isDev ? 'bg-primary-subtle border-primary-subtle' : 'bg-white') }}" data-reply-id="{{ $reply->id }}">
                                <div class="avatar-sm me-3">
                                    <span class="avatar-title bg-{{ $isInternal ? 'warning' : ($isDev ? 'primary' : 'secondary') }} text-white rounded-circle fw-bold fs-14">
                                        {{ strtoupper(substr($reply->sender_name, 0, 1)) }}
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div>
                                            <h6 class="fw-bold mb-0 fs-13 d-inline-block text-dark">{{ $reply->sender_name }}</h6>
                                            @if($isInternal)
                                                <span class="badge bg-warning text-dark font-monospace text-uppercase ms-1 fs-10">🔒 Internal Note</span>
                                            @elseif($isDev)
                                                <span class="badge bg-primary text-white font-monospace text-uppercase ms-1 fs-10">Developer</span>
                                            @else
                                                <span class="badge bg-secondary text-white font-monospace text-uppercase ms-1 fs-10">User</span>
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
                                <p class="mb-0 fs-13">No replies in thread yet. Post a response below.</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Post Reply Form -->
                    <div class="mt-4 pt-3 border-top">
                        <form id="form-ticket-reply" action="{{ route('admin.tickets.reply', $ticket->id) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <textarea class="form-control" name="message" id="reply-message-input" rows="3" required placeholder="Type your response to the user or internal dev note..."></textarea>
                            </div>
                            <div class="row align-items-center g-2">
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold fs-12 mb-1">Update Status</label>
                                    <select class="form-select form-select-sm" name="status" id="reply-status-select">
                                        <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>🟡 In Progress</option>
                                        <option value="waiting_user" {{ $ticket->status === 'waiting_user' ? 'selected' : '' }}>⏳ Waiting for User</option>
                                        <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>✅ Resolved</option>
                                        <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>⚫ Closed</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" name="is_internal_note" value="1" id="cb-internal">
                                        <label class="form-check-label fs-13 text-warning fw-bold" for="cb-internal">
                                            🔒 Internal Note (Only Devs see)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3 text-end mt-3">
                                    <button type="submit" class="btn btn-primary btn-sm fw-bold w-100" id="btn-submit-reply">
                                        <i class="mdi mdi-send me-1"></i>Post Response
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar: Ticket Control & Developer Assignee Panel -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-body-tertiary py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark"><i class="mdi mdi-cog-outline text-primary me-1"></i>Ticket Controls</h5>
                </div>
                <div class="card-body">
                    <!-- Assign Developer Form -->
                    <form id="form-ticket-assign" action="{{ route('admin.tickets.assign', $ticket->id) }}" method="POST" class="mb-4">
                        @csrf
                        <label class="form-label fw-semibold fs-13">Assign Developer</label>
                        <select class="form-select form-select-sm mb-2" name="assigned_developer_id" id="assign-dev-select">
                            <option value="">-- Unassigned --</option>
                            @foreach($developers as $dev)
                                <option value="{{ $dev->id }}" {{ $ticket->assigned_developer_id == $dev->id ? 'selected' : '' }}>
                                    👨‍💻 {{ $dev->name }} ({{ $dev->email }})
                                </option>
                            @endforeach
                        </select>
                        <label class="form-label fw-semibold fs-13">Application / service</label>
                        <input class="form-control form-control-sm mb-2" name="application_reference" maxlength="160" value="{{ $ticket->application_reference }}" placeholder="Application name">
                        <label class="form-label fw-semibold fs-13">Severity</label>
                        <select class="form-select form-select-sm mb-2" name="severity">
                            <option value="">Not classified</option>
                            @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $value => $label)<option value="{{ $value }}" @selected($ticket->severity === $value)>{{ $label }}</option>@endforeach
                        </select>
                        <label class="form-label fw-semibold fs-13">GitHub issue URL</label>
                        <input type="url" class="form-control form-control-sm mb-2" name="github_issue_url" maxlength="2048" value="{{ $ticket->github_issue_url }}" placeholder="https://github.com/owner/repo/issues/123">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100 fw-bold" id="btn-submit-assign">
                            <i class="mdi mdi-account-check me-1"></i>Update Assignee
                        </button>
                    </form>

                    <hr class="text-muted opacity-25">

                    <!-- Metadata Table -->
                    <h6 class="fw-bold text-dark mb-3 fs-13"><i class="mdi mdi-information-outline me-1"></i>Ticket Metadata</h6>
                    <table class="table table-sm table-borderless fs-13 mb-0">
                        <tr>
                            <td class="text-muted ps-0">Ticket Code:</td>
                            <td class="fw-bold font-monospace text-dark text-end pe-0">#{{ $ticket->ticket_code }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0">Priority:</td>
                            <td class="text-end pe-0">
                                <span class="badge bg-{{ match($ticket->priority) { 'urgent' => 'danger', 'high' => 'warning', default => 'info' } }} text-white">
                                    {{ strtoupper($ticket->priority) }}
                                </span>
                            </td>
                        </tr>
                        <tr><td class="text-muted ps-0">Application:</td><td class="text-dark text-end pe-0">{{ $ticket->application_reference ?: 'Not classified' }}</td></tr>
                        <tr><td class="text-muted ps-0">Severity:</td><td class="text-dark text-end pe-0">{{ str($ticket->severity ?: 'not classified')->title() }}</td></tr>
                        @if($ticket->github_issue_url)<tr><td class="text-muted ps-0">GitHub:</td><td class="text-end pe-0"><a href="{{ $ticket->github_issue_url }}" target="_blank" rel="noopener">Open issue</a></td></tr>@endif
                        <tr>
                            <td class="text-muted ps-0">Submitter Email:</td>
                            <td class="text-dark text-end pe-0">{{ $ticket->email }}</td>
                        </tr>
                        @if($ticket->phone)
                            <tr>
                                <td class="text-muted ps-0">WhatsApp:</td>
                                <td class="text-success text-end pe-0"><i class="mdi mdi-whatsapp me-1"></i>{{ $ticket->phone }}</td>
                            </tr>
                        @endif
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
            if ($(`.reply-bubble[data-reply-id="${data.id}"]`).length) return;

            $('#no-replies-placeholder').remove();

            const isDev = ['developer', 'admin'].includes(data.sender_type);
            const isInternal = data.is_internal_note;
            const firstLetter = (data.sender_name || 'U').substring(0, 1).toUpperCase();

            const bubbleHtml = `
                <div class="reply-bubble d-flex align-items-start mb-3 p-3 rounded-3 border ${isInternal ? 'bg-warning-subtle border-warning' : (isDev ? 'bg-primary-subtle border-primary-subtle' : 'bg-white')}" data-reply-id="${data.id}">
                    <div class="avatar-sm me-3">
                        <span class="avatar-title bg-${isInternal ? 'warning' : (isDev ? 'primary' : 'secondary')} text-white rounded-circle fw-bold fs-14">
                            ${firstLetter}
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div>
                                <h6 class="fw-bold mb-0 fs-13 d-inline-block text-dark">${data.sender_name}</h6>
                                ${isInternal 
                                    ? '<span class="badge bg-warning text-dark font-monospace text-uppercase ms-1 fs-10">🔒 Internal Note</span>' 
                                    : (isDev ? '<span class="badge bg-primary text-white font-monospace text-uppercase ms-1 fs-10">Developer</span>' : '<span class="badge bg-secondary text-white font-monospace text-uppercase ms-1 fs-10">User</span>')}
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
            if (data.status) {
                const statusBadge = document.getElementById('ticket-status-badge');
                if (statusBadge) {
                    statusBadge.textContent = data.status.replace('_', ' ').toUpperCase();
                    statusBadge.className = 'badge text-uppercase fw-bold fs-12 bg-' + 
                        (data.status === 'resolved' ? 'success' : (data.status === 'in_progress' ? 'primary' : 'info')) + '-subtle';
                }
            }

            // Scroll smoothly to newest message
            const threadList = document.getElementById('ticket-thread-list');
            threadList.scrollTop = threadList.scrollHeight;
        }

        // 1. Post Reply Form Submit Handler (ZERO ALERTS / TOASTS!)
        const formReply = document.getElementById('form-ticket-reply');
        if (formReply) {
            formReply.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();

                const messageInput = document.getElementById('reply-message-input');
                const messageText = messageInput.value.trim();

                if (!messageText) return;

                const btn = document.getElementById('btn-submit-reply');
                btn.disabled = true;

                $.ajax({
                    url: this.action,
                    type: 'POST',
                    data: $(this).serialize(),
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(resp) {
                        btn.disabled = false;
                        messageInput.value = '';

                        if (resp.success && resp.reply) {
                            appendReplyBubble({
                                id: resp.reply.id,
                                sender_type: resp.reply.sender_type,
                                sender_name: resp.reply.sender_name,
                                message: resp.reply.message,
                                is_internal_note: resp.reply.is_internal_note,
                                created_at: 'Just now',
                                status: $('#reply-status-select').val()
                            });
                        }

                        document.getElementById('cb-internal').checked = false;
                    },
                    error: function(xhr) {
                        btn.disabled = false;
                        alert('Failed to post reply: ' + (xhr.responseJSON?.message || 'Server error'));
                    }
                });
            }, true);
        }

        // 2. Assign Developer Form Handler (Quiet AJAX update)
        const formAssign = document.getElementById('form-ticket-assign');
        if (formAssign) {
            formAssign.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();

                const btn = document.getElementById('btn-submit-assign');
                btn.disabled = true;

                $.ajax({
                    url: this.action,
                    type: 'POST',
                    data: $(this).serialize(),
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(resp) {
                        btn.disabled = false;
                    },
                    error: function() {
                        btn.disabled = false;
                    }
                });
            }, true);
        }
    });
</script>
@endsection
