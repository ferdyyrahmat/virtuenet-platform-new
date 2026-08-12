@extends('layouts.vertical', ['title' => 'User Feedbacks'])

@section('content')
<div class="container-fluid">
    <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
        <div class="flex-grow-1">
            <h4 class="fs-18 fw-semibold m-0">User Feedbacks & Inquiries</h4>
        </div>
        <div class="text-end">
            <ol class="breadcrumb m-0 py-0">
                <li class="breadcrumb-item"><a href="{{ route('root') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Feedbacks</li>
            </ol>
        </div>
    </div>

    <!-- Alert Messages -->
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="mdi mdi-check-circle-outline me-1"></i>{{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Metric Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-12 text-uppercase fw-bold">Total Submissions</span>
                            <h4 class="fw-bold mb-0 text-primary mt-1">{{ $stats['total'] }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                                <i class="mdi mdi-inbox-multiple"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-12 text-uppercase fw-bold">Pending Review</span>
                            <h4 class="fw-bold mb-0 text-warning mt-1">{{ $stats['pending'] }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-20">
                                <i class="mdi mdi-clock-outline"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-12 text-uppercase fw-bold">Resolved</span>
                            <h4 class="fw-bold mb-0 text-success mt-1">{{ $stats['resolved'] }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20">
                                <i class="mdi mdi-check-circle-outline"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-12 text-uppercase fw-bold">Average Rating</span>
                            <h4 class="fw-bold mb-0 text-dark mt-1">
                                <i class="mdi mdi-star text-warning me-1"></i>{{ $stats['avg_rating'] }} <small class="fs-12 text-muted">/ 5.0</small>
                            </h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-info-subtle text-info rounded-circle fs-20">
                                <i class="mdi mdi-star-half-full"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Feedbacks Card -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-title mb-0 fw-bold text-body"><i class="mdi mdi-message-text-outline text-primary me-1"></i>User Feedback Entries</h5>
                    <button type="button" class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modal-submit-feedback">
                        <i class="mdi mdi-plus-circle-outline me-1"></i>Submit New Feedback
                    </button>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-13">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Submitter</th>
                                    <th>Category / Subject</th>
                                    <th>Rating</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($feedbacks as $fb)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-xs me-2">
                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle fw-bold">
                                                        {{ strtoupper(substr($fb->name, 0, 1)) }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0 fs-13">{{ $fb->name }}</h6>
                                                    <small class="text-muted">{{ $fb->email }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @php
                                                $badgeClass = match($fb->category) {
                                                    'bug' => 'danger',
                                                    'feature_request' => 'info',
                                                    'complaint' => 'warning',
                                                    default => 'secondary'
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $badgeClass }}-subtle text-{{ $badgeClass }} font-monospace text-uppercase me-1">
                                                {{ str_replace('_', ' ', $fb->category) }}
                                            </span>
                                            <span class="fw-semibold text-dark">{{ $fb->subject }}</span>
                                            <p class="text-muted mb-0 fs-12 text-truncate d-block mt-1" style="max-width: 380px;">{{ $fb->message }}</p>
                                        </td>
                                        <td>
                                            <div class="text-warning">
                                                @for($i = 1; $i <= 5; $i++)
                                                    @if($i <= $fb->rating)
                                                        <i class="mdi mdi-star"></i>
                                                    @else
                                                        <i class="mdi mdi-star-outline text-muted"></i>
                                                    @endif
                                                @endfor
                                            </div>
                                        </td>
                                        <td>
                                            @php
                                                $statusBadge = match($fb->status) {
                                                    'resolved' => 'success',
                                                    'reviewed' => 'info',
                                                    default => 'warning'
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $statusBadge }}-subtle text-{{ $statusBadge }} fw-bold text-uppercase">
                                                <i class="mdi {{ $fb->status == 'resolved' ? 'mdi-check-circle' : ($fb->status == 'reviewed' ? 'mdi-eye-outline' : 'mdi-clock-outline') }} me-1"></i>{{ $fb->status }}
                                            </span>
                                        </td>
                                        <td class="text-muted fs-12">{{ $fb->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="text-end pe-3">
                                            <button type="button" class="btn btn-outline-primary btn-xs me-1 btn-review-fb"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modal-review-feedback"
                                                data-id="{{ $fb->id }}"
                                                data-category="{{ $fb->category }}"
                                                data-subject="{{ e($fb->subject) }}"
                                                data-message="{{ e($fb->message) }}"
                                                data-status="{{ $fb->status }}"
                                                data-notes="{{ e($fb->admin_notes ?? '') }}">
                                                <i class="mdi mdi-pencil-outline me-1"></i>Review
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-xs" onclick="deleteFeedback({{ $fb->id }})">
                                                <i class="mdi mdi-trash-can-outline"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="mdi mdi-inbox-outline fs-36 text-muted d-block mb-2"></i>
                                            <p class="mb-0 fw-semibold text-dark">No feedback submissions found.</p>
                                            <small>Click "Submit New Feedback" to test user feedback entry.</small>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Submit Feedback -->
<div class="modal fade" id="modal-submit-feedback" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary-subtle py-3">
                <h5 class="modal-title fw-bold text-primary"><i class="mdi mdi-message-plus-outline me-1"></i>Submit User Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.feedbacks.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject / Title</label>
                        <input type="text" class="form-control" name="subject" required placeholder="e.g. Dark Mode contrast improvement">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <select class="form-select" name="category" required>
                            <option value="feature_request">💡 Feature Request</option>
                            <option value="bug">🐛 Bug Report</option>
                            <option value="general_inquiry">💬 General Inquiry</option>
                            <option value="complaint">⚠️ Complaint</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rating (1 - 5 Stars)</label>
                        <select class="form-select" name="rating" required>
                            <option value="5" selected>⭐⭐⭐⭐⭐ (5/5) Excellent</option>
                            <option value="4">⭐⭐⭐⭐ (4/5) Very Good</option>
                            <option value="3">⭐⭐⭐ (3/5) Average</option>
                            <option value="2">⭐⭐ (2/5) Poor</option>
                            <option value="1">⭐ (1/5) Very Poor</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Detailed Message</label>
                        <textarea class="form-control" name="message" rows="4" required placeholder="Describe your experience or feature request..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="mdi mdi-send me-1"></i>Submit Feedback</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Review & Change Status -->
<div class="modal fade" id="modal-review-feedback" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-body-tertiary py-3">
                <h5 class="modal-title fw-bold text-body"><i class="mdi mdi-pencil-box-outline me-1 text-primary"></i>Review Feedback #<span id="review-fb-id"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-update-status" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="card bg-body-tertiary border mb-3 p-3 rounded-3">
                        <span class="badge bg-primary-subtle text-primary font-monospace text-uppercase mb-1" style="width: fit-content;" id="review-fb-category"></span>
                        <h6 class="fw-bold text-dark mb-1" id="review-fb-subject"></h6>
                        <p class="text-muted fs-13 mb-0" id="review-fb-message"></p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Feedback Status</label>
                        <select class="form-select" name="status" id="review-fb-status" required>
                            <option value="pending">⏳ Pending Review</option>
                            <option value="reviewed">👀 Reviewed (In Progress)</option>
                            <option value="resolved">✅ Resolved</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Admin Notes / Response</label>
                        <textarea class="form-control" name="admin_notes" id="review-fb-notes" rows="3" placeholder="Add internal resolution notes or user response..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold" id="btn-save-review"><i class="mdi mdi-content-save-outline me-1"></i>Save Status</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script-bottom')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var modalReview = document.getElementById('modal-review-feedback');
        if (modalReview) {
            modalReview.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                if (!button) return;

                var id = button.getAttribute('data-id');
                var category = button.getAttribute('data-category');
                var subject = button.getAttribute('data-subject');
                var message = button.getAttribute('data-message');
                var status = button.getAttribute('data-status');
                var notes = button.getAttribute('data-notes');

                document.getElementById('review-fb-id').textContent = id;
                document.getElementById('review-fb-category').textContent = category ? category.replace('_', ' ') : '';
                document.getElementById('review-fb-subject').textContent = subject || '';
                document.getElementById('review-fb-message').textContent = message || '';
                document.getElementById('review-fb-status').value = status || 'pending';
                document.getElementById('review-fb-notes').value = notes || '';

                var form = document.getElementById('form-update-status');
                if (form) {
                    form.action = '{{ url("admin/feedbacks") }}/' + id + '/status';
                }
            });
        }
    });

    function deleteFeedback(id) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete Feedback #' + id + '?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, Delete'
            }).then((res) => {
                if (res.isConfirmed) {
                    $.ajax({
                        url: '/admin/feedbacks/' + id,
                        type: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        success: function(resp) {
                            Swal.fire('Deleted!', resp.message, 'success').then(() => {
                                window.location.href = resp.redirect || window.location.href;
                            });
                        }
                    });
                }
            });
        } else {
            if (confirm('Delete Feedback #' + id + '?')) {
                $.ajax({
                    url: '/admin/feedbacks/' + id,
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function(resp) {
                        window.location.href = resp.redirect || window.location.href;
                    }
                });
            }
        }
    }
</script>
@endsection
