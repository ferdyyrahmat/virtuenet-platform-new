@extends('layouts.vertical', ['title' => __('messages.cloud_directory')])

@section('content')
<div class="container-fluid">
    <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
        <div class="flex-grow-1">
            <h4 class="fs-18 fw-semibold m-0">{{ __('messages.cloud_directory') }}</h4>
        </div>
        <div class="text-end">
            <ol class="breadcrumb m-0 py-0">
                <li class="breadcrumb-item"><a href="{{ route('root') }}">{{ __('messages.dashboard') }}</a></li>
                <li class="breadcrumb-item active">{{ __('messages.cloud_directory') }}</li>
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

    @if ($errorMsg)
        <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
            <i class="mdi mdi-alert-outline me-1"></i>{{ $errorMsg }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Storage Status & Action Buttons -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3 d-flex flex-wrap align-items-center justify-content-between g-2">
                    <div class="d-flex align-items-center me-3">
                        <div class="avatar-sm me-2">
                            <span class="avatar-title bg-info-subtle text-info rounded-circle fs-20">
                                <i class="mdi mdi-folder-network-outline"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0 fs-14">Laravel Filesystem Storage</h6>
                            <small class="text-muted">Disk: {{ config('filesystems.default') }}</small>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        @if(auth()->user()->can('admin.directory.folder'))
                            <button type="button" class="btn btn-outline-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modal-new-folder">
                                <i class="mdi mdi-folder-plus-outline me-1"></i>{{ __('messages.new_folder') }}
                            </button>
                        @endif
                        @if(auth()->user()->can('admin.directory.upload'))
                            <button type="button" class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modal-upload-file">
                                <i class="mdi mdi-upload me-1"></i>{{ __('messages.upload_file') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Breadcrumb Path Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2 px-3 bg-body-tertiary">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-13 align-items-center">
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin.directory.index') }}" class="fw-bold text-primary">
                            <i class="mdi mdi-database me-1"></i>{{ __('messages.root_bucket') }}
                        </a>
                    </li>
                    @foreach($breadcrumbs as $b)
                        <li class="breadcrumb-item {{ $loop->last ? 'active text-dark fw-bold' : '' }}">
                            @if(!$loop->last)
                                <a href="{{ route('admin.directory.index', ['path' => $b['path']]) }}">{{ $b['name'] }}</a>
                            @else
                                {{ $b['name'] }}
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        </div>
    </div>

    <!-- Directories / Folders Grid -->
    @if(count($directories) > 0)
        <div class="row g-3 mb-4">
            @foreach($directories as $dir)
                <div class="col-md-3 col-sm-6">
                    <div class="card border shadow-none hover-shadow position-relative mb-0 rounded-3">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <a href="{{ route('admin.directory.index', ['path' => $dir['path']]) }}" class="text-decoration-none d-flex align-items-center text-truncate">
                                <i class="mdi mdi-folder text-warning fs-28 me-2"></i>
                                <span class="fw-bold text-dark fs-13 text-truncate">{{ $dir['name'] }}</span>
                            </a>
                            <button type="button" class="btn btn-link text-danger p-0 ms-2" onclick="deleteItem('{{ e($dir['path']) }}', 'folder')">
                                <i class="mdi mdi-trash-can-outline fs-16"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Files Table -->
    @if(count($files) > 0)
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-body-tertiary py-3">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="mdi mdi-file-multiple-outline text-primary me-1"></i>{{ empty($currentPath) ? __('messages.root_bucket') : __('messages.root_bucket') . ' / ' . $currentPath }}
                        </h5>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-13">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">{{ __('messages.file_name') }}</th>
                                        <th>{{ __('messages.file_size') }}</th>
                                        <th>{{ __('messages.timestamp') }}</th>
                                        <th class="text-end pe-3">{{ __('messages.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($files as $file)
                                        <tr>
                                            <td class="ps-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="mdi {{ $file['icon'] }} fs-24 me-2"></i>
                                                    <span class="fw-semibold text-dark">{{ $file['name'] }}</span>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-secondary-subtle text-dark fs-12">{{ $file['size_formatted'] }}</span></td>
                                            <td class="text-muted fs-12"><i class="mdi mdi-clock-outline me-1"></i>{{ $file['last_modified'] }}</td>
                                            <td class="text-end pe-3">
                                                <a href="{{ route('admin.directory.download', ['path' => $file['path']]) }}" class="btn btn-outline-primary btn-xs me-1">
                                                    <i class="mdi mdi-download me-1"></i>{{ __('messages.download') }}
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-xs" onclick="deleteItem('{{ e($file['path']) }}', 'file')">
                                                    <i class="mdi mdi-trash-can-outline"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @elseif(count($directories) == 0)
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm text-center py-5">
                    <div class="card-body">
                        <i class="mdi mdi-folder-open-outline fs-48 text-muted d-block mb-2"></i>
                        <h6 class="fw-bold text-dark">{{ __('messages.no_data') }}</h6>
                        <p class="text-muted fs-13 mb-3">Click "{{ __('messages.upload_file') }}" or "{{ __('messages.new_folder') }}" to add items.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Modal: New Folder -->
<div class="modal fade" id="modal-new-folder" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold text-white fs-15"><i class="mdi mdi-folder-plus-outline me-1"></i>{{ __('messages.new_folder') }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.directory.folder') }}" method="POST">
                @csrf
                <input type="hidden" name="path" value="{{ $currentPath }}">
                <div class="modal-body">
                    <label class="form-label fw-semibold fs-13">{{ __('messages.folder_name') }}</label>
                    <input type="text" class="form-control" name="folder_name" required placeholder="e.g. documents">
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold">{{ __('messages.create') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Upload File -->
<div class="modal fade" id="modal-upload-file" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold text-white fs-15"><i class="mdi mdi-upload me-1"></i>{{ __('messages.upload_file') }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.directory.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="path" value="{{ $currentPath }}">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">{{ __('messages.target_path') }}</label>
                        <input type="text" class="form-control bg-light" readonly value="{{ empty($currentPath) ? __('messages.root_bucket') : '/' . $currentPath }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">{{ __('messages.select_file') }}</label>
                        <input type="file" class="form-control" name="file" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="mdi mdi-upload me-1"></i>{{ __('messages.upload_file') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
@section('script-bottom')
<script>
    function deleteItem(path, type) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '{{ __("messages.confirm_delete") }}',
                text: path,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: '{{ __("messages.yes_delete") }}',
                cancelButtonText: '{{ __("messages.cancel") }}'
            }).then((res) => {
                if (res.isConfirmed) {
                    $.ajax({
                        url: "{{ route('admin.directory.destroy') }}",
                        type: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data: { path: path, type: type },
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
