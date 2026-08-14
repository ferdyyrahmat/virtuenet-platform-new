@extends('layouts.vertical', ['title' => __('messages.manage_support_tickets')])

@section('css')
    @vite([
        'node_modules/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
        'node_modules/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css'
    ])
@endsection

@section('content')
<div class="container-fluid">
    <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
        <div class="flex-grow-1">
            <h4 class="fs-18 fw-semibold m-0">{{ __('messages.manage_support_tickets') }}</h4>
        </div>
        <div class="text-end">
            @if(auth()->user()->can('admin.tickets.developers.index'))
                <a href="{{ route('admin.tickets.developers.index') }}" class="btn btn-outline-primary btn-sm me-2 fw-bold">
                    <i class="mdi mdi-account-code-outline me-1"></i>{{ __('messages.manage_developers') }}
                </a>
            @endif
            <ol class="breadcrumb m-0 py-0 d-inline-flex align-items-center">
                <li class="breadcrumb-item"><a href="{{ route('root') }}">{{ __('messages.dashboard') }}</a></li>
                <li class="breadcrumb-item active">{{ __('messages.support_tickets') }}</li>
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

    <!-- Ticket Metric Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-12 text-uppercase fw-bold">{{ __('messages.support_tickets') }}</span>
                            <h4 class="fw-bold mb-0 text-primary mt-1">{{ $stats['total'] }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                                <i class="mdi mdi-ticket-account"></i>
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
                            <span class="text-muted fs-12 text-uppercase fw-bold">{{ __('messages.open') }}</span>
                            <h4 class="fw-bold mb-0 text-danger mt-1">{{ $stats['open'] }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-20">
                                <i class="mdi mdi-alert-circle-outline"></i>
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
                            <span class="text-muted fs-12 text-uppercase fw-bold">{{ __('messages.in_progress') }}</span>
                            <h4 class="fw-bold mb-0 text-warning mt-1">{{ $stats['in_progress'] }}</h4>
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
                            <span class="text-muted fs-12 text-uppercase fw-bold">{{ __('messages.resolved') }}</span>
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
    </div>

    <!-- Tickets List Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-title mb-0 fw-bold text-body"><i class="mdi mdi-ticket-confirmation-outline text-primary me-1"></i>{{ __('messages.manage_support_tickets') }}</h5>
                    <div class="d-flex gap-2">
                        <form method="GET" action="{{ route('admin.tickets.index') }}" class="d-flex gap-2">
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">-- {{ __('messages.all_statuses') }} --</option>
                                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>🟢 {{ __('messages.open') }}</option>
                                <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>🟡 {{ __('messages.in_progress') }}</option>
                                <option value="waiting_user" {{ request('status') === 'waiting_user' ? 'selected' : '' }}>⏳ {{ __('messages.waiting_user') }}</option>
                                <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>🔵 {{ __('messages.resolved') }}</option>
                            </select>
                        </form>
                    </div>
                </div>

                <div class="card-body">
                    <table id="tickets-datatable" class="table table-hover align-middle mb-0 fs-13 w-100">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('messages.ticket_code') }}</th>
                                <th>{{ __('messages.ticket_user') }}</th>
                                <th>{{ __('messages.subject') }} / {{ __('messages.category') }}</th>
                                <th>{{ __('messages.priority') }}</th>
                                <th>{{ __('messages.assigned_dev') }}</th>
                                <th>{{ __('messages.status') }}</th>
                                <th>{{ __('messages.created_at') }}</th>
                                <th class="text-end">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script-bottom')
    @vite([
        'resources/js/pages/datatable.init.js'
    ])

    <script>
        $(document).ready(function() {
            var table = $('#tickets-datatable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('admin.tickets.index') }}",
                    type: "GET",
                    data: function(d) {
                        d.status = $('select[name="status"]').val();
                    }
                },
                columns: [
                    { data: 'ticket_code', name: 'ticket_code' },
                    { data: 'user', name: 'user', orderable: false, searchable: false },
                    { data: 'subject_category', name: 'subject', orderable: true, searchable: false },
                    { data: 'priority', name: 'priority' },
                    { data: 'assigned_dev', name: 'assigned_dev', orderable: false, searchable: false },
                    { data: 'status', name: 'status' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false }
                ],
                drawCallback: function() {
                    $("#tickets-datatable_length select").addClass('form-select form-select-sm');
                    $(".dataTables_length label").addClass('form-label');
                }
            });
        });

        function deleteTicket(id, code) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '{{ __("messages.confirm_delete") }}',
                    text: '#' + code,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: '{{ __("messages.yes_delete") }}',
                    cancelButtonText: '{{ __("messages.cancel") }}'
                }).then((res) => {
                    if (res.isConfirmed) {
                        $.ajax({
                            url: '/admin/tickets/' + id,
                            type: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                            success: function(resp) {
                                Swal.fire('Deleted!', resp.message, 'success').then(() => {
                                    $('#tickets-datatable').DataTable().ajax.reload();
                                });
                            }
                        });
                    }
                });
            }
        }
    </script>
@endsection
