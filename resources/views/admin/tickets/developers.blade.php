@extends('layouts.vertical', ['title' => 'Developer Team Settings'])

@section('content')
<div class="container-fluid">
    <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
        <div class="flex-grow-1">
            <h4 class="fs-18 fw-semibold m-0">Developer Team & Notification Channels</h4>
        </div>
        <div class="text-end">
            <ol class="breadcrumb m-0 py-0">
                <li class="breadcrumb-item"><a href="{{ route('root') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.tickets.index') }}">Tickets</a></li>
                <li class="breadcrumb-item active">Developer Team</li>
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

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-3">
                    <div>
                        <h5 class="card-title mb-1 text-body fw-bold"><i class="mdi mdi-code-tags text-primary me-1"></i>Developer Roster & Alert Channels</h5>
                        <p class="text-muted fs-13 mb-0">Register developers manually or link system users to receive ticket alerts in-app.</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modal-add-developer">
                        <i class="mdi mdi-plus-circle-outline me-1"></i>Add New Developer
                    </button>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-13">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Developer Name</th>
                                    <th>Contact Information</th>
                                    <th>Linked User Account</th>
                                    <th>Active Notification Channels</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($developers as $dev)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-xs me-2">
                                                    <span class="avatar-title bg-info-subtle text-info rounded-circle fw-bold">
                                                        {{ strtoupper(substr($dev->name, 0, 1)) }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0 fs-13">{{ $dev->name }}</h6>
                                                    <small class="text-muted">ID: #{{ $dev->id }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><i class="mdi mdi-email-outline text-muted me-1"></i>{{ $dev->email }}</div>
                                        </td>
                                        <td>
                                            @if($dev->user)
                                                <span class="badge bg-primary-subtle text-primary font-monospace">
                                                    <i class="mdi mdi-account-check me-1"></i>{{ $dev->user->name }}
                                                </span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-muted">External / Manual</span>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary"><i class="mdi mdi-bell-outline"></i> In-App</span></td>
                                        <td>
                                            @if($dev->is_active)
                                                <span class="badge bg-success-subtle text-success fw-bold text-uppercase"><i class="mdi mdi-check-circle me-1"></i>Active</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-muted fw-bold text-uppercase">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <button type="button" class="btn btn-outline-primary btn-xs me-1 btn-edit-dev"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modal-edit-developer"
                                                data-id="{{ $dev->id }}"
                                                data-name="{{ e($dev->name) }}"
                                                data-email="{{ e($dev->email) }}"
                                                data-userid="{{ $dev->user_id }}"
                                                data-active="{{ $dev->is_active ? 1 : 0 }}">
                                                <i class="mdi mdi-pencil-outline me-1"></i>Edit
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-xs" onclick="deleteDeveloper({{ $dev->id }}, '{{ e($dev->name) }}')">
                                                <i class="mdi mdi-trash-can-outline"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="mdi mdi-account-outline fs-36 text-muted d-block mb-2"></i>
                                            <p class="mb-0 fw-semibold text-dark">No developers added yet.</p>
                                            <small>Click "Add New Developer" to add developers to receive ticket alerts.</small>
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

<!-- Modal: Add Developer -->
<div class="modal fade" id="modal-add-developer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold text-white fs-15"><i class="mdi mdi-account-plus-outline me-1"></i>Add New Developer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.tickets.developers.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Link System User (Optional)</label>
                        <select class="form-select" name="user_id" id="add-dev-user-select">
                            <option value="">-- Manual External Developer --</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" data-name="{{ $u->name }}" data-email="{{ $u->email }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Full Name</label>
                        <input type="text" class="form-control" name="name" id="add-dev-name" required placeholder="e.g. Alex Rivera">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Email Address (For Alerts)</label>
                        <input type="email" class="form-control" name="email" id="add-dev-email" required placeholder="alex@dev.com">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="mdi mdi-content-save-outline me-1"></i>Save Developer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Developer -->
<div class="modal fade" id="modal-edit-developer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold text-white fs-15"><i class="mdi mdi-pencil-box-outline me-1"></i>Edit Developer #<span id="edit-dev-id-title"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-edit-dev" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Link System User (Optional)</label>
                        <select class="form-select" name="user_id" id="edit-dev-user-select">
                            <option value="">-- Manual External Developer --</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Full Name</label>
                        <input type="text" class="form-control" name="name" id="edit-dev-name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Email Address</label>
                        <input type="email" class="form-control" name="email" id="edit-dev-email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Status</label>
                        <select class="form-select" name="is_active" id="edit-dev-active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="mdi mdi-content-save-outline me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script-bottom')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var addSelect = document.getElementById('add-dev-user-select');
        if (addSelect) {
            addSelect.addEventListener('change', function() {
                var opt = this.options[this.selectedIndex];
                if (opt && opt.value) {
                    document.getElementById('add-dev-name').value = opt.getAttribute('data-name') || '';
                    document.getElementById('add-dev-email').value = opt.getAttribute('data-email') || '';
                }
            });
        }

        var editModal = document.getElementById('modal-edit-developer');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function(event) {
                var btn = event.relatedTarget;
                if (!btn) return;

                var id = btn.getAttribute('data-id');
                var name = btn.getAttribute('data-name');
                var email = btn.getAttribute('data-email');
                var userId = btn.getAttribute('data-userid');
                var active = btn.getAttribute('data-active');
                document.getElementById('edit-dev-id-title').textContent = id;
                document.getElementById('edit-dev-user-select').value = userId || '';
                document.getElementById('edit-dev-name').value = name || '';
                document.getElementById('edit-dev-email').value = email || '';
                document.getElementById('edit-dev-active').value = active || '1';
                document.getElementById('form-edit-dev').action = '{{ url("admin/tickets/developers") }}/' + id;
            });
        }
    });

    function deleteDeveloper(id, name) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete Developer ' + name + '?',
                text: 'This developer will stop receiving ticket alerts.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, Delete'
            }).then((res) => {
                if (res.isConfirmed) {
                    $.ajax({
                        url: '/admin/tickets/developers/' + id,
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
        }
    }
</script>
@endsection
