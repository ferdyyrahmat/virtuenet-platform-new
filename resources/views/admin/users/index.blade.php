@extends('layouts.vertical', ['title' => __('messages.user_management')])

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
            <h4 class="fs-18 fw-semibold m-0">{{ __('messages.user_management') }}</h4>
        </div>

        <div class="text-end">
            <ol class="breadcrumb m-0 py-0">
                <li class="breadcrumb-item"><a href="{{ route('root') }}">{{ __('messages.dashboard') }}</a></li>
                <li class="breadcrumb-item active">{{ __('messages.user_management') }}</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="mdi mdi-account-group me-1"></i>{{ __('messages.user_management') }}</h5>
                    @if(auth()->user()->can('create users'))
                        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm"><i class="mdi mdi-plus me-1"></i>{{ __('messages.create') }}</a>
                    @endif
                </div><!-- end card header -->

                <div class="card-body">
                    <table id="users-datatable" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>{{ __('messages.full_name') }}</th>
                                <th>{{ __('messages.email_address') }}</th>
                                <th>{{ __('messages.roles_permissions') }}</th>
                                <th>{{ __('messages.created_at') }}</th>
                                <th class="text-center">{{ __('messages.actions') }}</th>
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
            var table = $('#users-datatable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('admin.users.index') }}",
                    type: "GET"
                },
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'name', name: 'name' },
                    { data: 'email', name: 'email' },
                    { data: 'roles', name: 'roles', orderable: false, searchable: false },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false }
                ],
                drawCallback: function() {
                    $("#users-datatable_length select").addClass('form-select form-select-sm');
                    $(".dataTables_length label").addClass('form-label');
                }
            });
        });

        function deleteUser(id, url) {
            Swal.fire({
                title: '{{ __("messages.confirm_delete") }}',
                text: "ID: " + id,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '{{ __("messages.yes_delete") }}',
                cancelButtonText: '{{ __("messages.cancel") }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        type: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}' },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Deleted!', response.message, 'success').then(() => {
                                    if (response.redirect) {
                                        window.location.href = response.redirect;
                                    } else {
                                        $('#users-datatable').DataTable().ajax.reload();
                                    }
                                });
                            } else {
                                Swal.fire('Failed!', response.message || 'Something went wrong.', 'error');
                            }
                        },
                        error: function(xhr) {
                            Swal.fire('Error!', xhr.responseJSON?.message || 'Access Denied or Server Error.', 'error');
                        }
                    });
                }
            })
        }
    </script>
@endsection
