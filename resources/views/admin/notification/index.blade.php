@extends('layouts.vertical', ['title' => __('messages.blast_title')])

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Select2 container full-width */
    .notification-select2 + .select2-container { width: 100% !important; }

    /* Single selection styling to match form-select */
    .notification-select2 + .select2-container .select2-selection--single {
        height: 42px;
        padding: 6px 12px;
        border: 1px solid var(--bs-border-color);
        border-radius: .375rem;
        background: var(--bs-body-bg);
        color: var(--bs-body-color);
        transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
    }
    .notification-select2 + .select2-container .select2-selection--single:hover {
        border-color: var(--bs-primary);
    }
    .notification-select2 + .select2-container--default.select2-container--focus .select2-selection--single,
    .notification-select2 + .select2-container--default.select2-container--open .select2-selection--single {
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 0.15rem rgba(var(--bs-primary-rgb), .25);
    }
    .notification-select2 + .select2-container .select2-selection__rendered {
        color: var(--bs-body-color);
        line-height: 28px;
        padding-left: 0;
    }
    .notification-select2 + .select2-container .select2-selection__arrow {
        height: 40px;
        right: 8px;
    }

    /* Dropdown styling */
    .select2-container--default .select2-dropdown {
        border: 1px solid var(--bs-primary);
        background: var(--bs-body-bg);
        color: var(--bs-body-color);
        box-shadow: 0 8px 24px rgba(0, 0, 0, .18);
        border-radius: .375rem;
        overflow: hidden;
    }
    .select2-container--default .select2-search--dropdown {
        padding: 8px;
        background: var(--bs-body-bg);
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
        border: 1px solid var(--bs-border-color);
        border-radius: .3rem;
        background: var(--bs-tertiary-bg);
        color: var(--bs-body-color);
        padding: 6px 10px;
        outline: none;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field:focus {
        border-color: var(--bs-primary);
    }
    .select2-container--default .select2-results__option {
        padding: 8px 12px;
        transition: background .15s;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: var(--bs-primary);
        color: #fff;
    }
    .select2-container--default .select2-results__option[aria-selected="true"] {
        background: rgba(var(--bs-primary-rgb), .1);
        color: var(--bs-primary);
        font-weight: 600;
    }

    /* Dark mode overrides */
    [data-bs-theme="dark"] .select2-container--default .select2-selection--single {
        background: #0f172a;
        border-color: #334155;
    }
    [data-bs-theme="dark"] .select2-container--default .select2-dropdown {
        background: #0f172a;
        border-color: #334155;
    }
    [data-bs-theme="dark"] .select2-container--default .select2-search--dropdown .select2-search__field {
        background: #1e293b;
        border-color: #334155;
        color: #e2e8f0;
    }
    [data-bs-theme="dark"] .select2-container--default .select2-results__option {
        color: #cbd5e1;
    }
    [data-bs-theme="dark"] .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: var(--bs-primary);
        color: #fff;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
        <div class="flex-grow-1">
            <h4 class="fs-18 fw-semibold m-0">{{ __('messages.blast_title') }}</h4>
        </div>
        <div class="text-end">
            <ol class="breadcrumb m-0 py-0">
                <li class="breadcrumb-item"><a href="{{ route('root') }}">{{ __('messages.dashboard') }}</a></li>
                <li class="breadcrumb-item active">{{ __('messages.notification_blast') }}</li>
            </ol>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="mdi mdi-check-circle-outline me-1"></i>{{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="mdi mdi-alert-circle-outline me-1"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Main Navigation Tabs -->
    <ul class="nav nav-tabs nav-bordered mb-3" role="tablist">
        <li class="nav-item">
            <a href="#tab-broadcast" data-bs-toggle="tab" aria-expanded="true" class="nav-item-tab nav-link active py-2">
                <i class="mdi mdi-bullhorn-outline fs-18 me-1 align-middle"></i>
                <span class="fw-semibold fs-14">Broadcast Blast</span>
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- TAB 1: BROADCAST BLAST -->
        <div class="tab-pane show active" id="tab-broadcast">
            <div class="row">
                <!-- Left: Create Blast Form -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-primary-subtle py-3">
                            <h5 class="card-title mb-0 text-primary fw-bold">
                                <i class="mdi mdi-send-outline me-1"></i>{{ __('messages.send_blast') }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="form-send-blast" action="{{ route('admin.notifications.send-blast') }}" method="POST">
                                @csrf
                                <input type="hidden" name="target_id" id="hidden-target-id">

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">{{ __('messages.notif_title') }}</label>
                                    <input type="text" class="form-control" name="title" required placeholder="e.g. System Maintenance Notice">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Type</label>
                                    <div class="d-flex gap-2">
                                        <label class="btn btn-outline-info btn-sm flex-fill active">
                                            <input type="radio" name="type" value="info" checked class="d-none"> <i class="mdi mdi-information-outline me-1"></i>Info
                                        </label>
                                        <label class="btn btn-outline-success btn-sm flex-fill">
                                            <input type="radio" name="type" value="success" class="d-none"> <i class="mdi mdi-check-circle-outline me-1"></i>Success
                                        </label>
                                        <label class="btn btn-outline-warning btn-sm flex-fill">
                                            <input type="radio" name="type" value="warning" class="d-none"> <i class="mdi mdi-alert-outline me-1"></i>Warning
                                        </label>
                                        <label class="btn btn-outline-danger btn-sm flex-fill">
                                            <input type="radio" name="type" value="danger" class="d-none"> <i class="mdi mdi-alert-circle-outline me-1"></i>Danger
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">{{ __('messages.target_audience') }}</label>
                                    <select class="form-select notification-select2 mb-2" name="target_type" id="select-target-type">
                                        <option value="all">🌐 {{ __('messages.all_users') }}</option>
                                        <option value="role">👥 {{ __('messages.user_roles') }}</option>
                                        <option value="user">👤 {{ __('messages.user') }}</option>
                                    </select>

                                    <div id="target-role-container" class="mb-2" style="display:none;">
                                        <select class="form-select notification-select2" id="select-target-role" data-placeholder="-- Select Role --">
                                            <option value="">-- Select Role --</option>
                                            @foreach($roles as $role)
                                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div id="target-user-container" class="mb-2" style="display:none;">
                                        <select class="form-select notification-select2" id="select-target-user" data-placeholder="-- Select User --">
                                            <option value="">-- Select User --</option>
                                            @foreach($users as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- In-app notification channel -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">{{ __('messages.notification_channels') }}</label>
                                    <div class="card bg-body-tertiary border p-3 rounded-3">
                                        <span class="badge bg-primary-subtle text-primary">
                                            <i class="mdi mdi-bell-outline me-1"></i>{{ __('messages.inapp_channel') }}
                                        </span>
                                    </div>
                                </div>

                <div class="mb-3">
                                    <label class="form-label fw-semibold">{{ __('messages.notif_message') }}</label>
                                    <textarea class="form-control" name="message" rows="4" required placeholder="Write message..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 fw-bold py-2" id="btn-dispatch-blast">
                                    <i class="mdi mdi-send me-1"></i>{{ __('messages.send_blast') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right: Blast History Table -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-body-tertiary py-3">
                            <h5 class="card-title mb-0 fw-bold text-body"><i class="mdi mdi-history me-1 text-primary"></i>{{ __('messages.activity_logs') }}</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 fs-13">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">{{ __('messages.description') }}</th>
                                            <th>{{ __('messages.target_audience') }}</th>
                                            <th>{{ __('messages.notification_channels') }}</th>
                                            <th>Status</th>
                                            <th>{{ __('messages.timestamp') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($blasts as $b)
                                            <tr>
                                                <td class="ps-3">
                                                    <span class="badge bg-{{ $b->type }}-subtle text-{{ $b->type }} mb-1 font-monospace text-uppercase">{{ $b->type }}</span>
                                                    <h6 class="fw-bold text-dark mb-0 fs-14">{{ $b->title }}</h6>
                                                    <small class="text-muted text-truncate d-inline-block style-max-width">{{ $b->message }}</small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary-subtle text-primary font-monospace">{{ strtoupper($b->target_type) }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary-subtle text-primary me-1">
                                                        <i class="mdi mdi-bell-outline"></i> In-App
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-success fw-bold me-1"><i class="mdi mdi-check-circle-outline"></i> {{ $b->sent_count }}</span>
                                                </td>
                                                <td class="text-muted fs-12">{{ $b->created_at->format('Y-m-d H:i') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-5 text-muted">
                                                    <i class="mdi mdi-bullhorn-outline fs-36 text-muted d-block mb-2"></i>
                                                    <p class="mb-0 fw-semibold text-dark">{{ __('messages.no_data') }}</p>
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


    </div>
</div>
@endsection

@section('script-bottom')
{{-- 
    Select2 CDN loaded here (synchronous). At this point in the HTML parse:
    - jQuery CDN (from vendor.blade) is already loaded → window.jQuery exists
    - Vite module (app.js) has NOT executed yet (type="module" = deferred)
    So Select2 CDN attaches itself to the CDN jQuery → window.jQuery.fn.select2 ✓
--}}
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
// IIFE captures window.jQuery RIGHT NOW (CDN jQuery + Select2 attached).
// This runs BEFORE Vite's deferred module overwrites window.jQuery.
// The closure preserves this reference permanently.
(function($) {
    if (!$ || !$.fn || !$.fn.select2) {
        console.error('[Notification Blast] Select2 failed to load.');
        return;
    }

    // jQuery.ready fires after all deferred scripts + DOMContentLoaded
    $(function () {
        var $type     = $('#select-target-type');
        var $role     = $('#select-target-role');
        var $user     = $('#select-target-user');
        var $targetId = $('#hidden-target-id');
        var $roleBox  = $('#target-role-container');
        var $userBox  = $('#target-user-container');

        var roleReady = false;
        var userReady = false;

        // Target type select — always visible, init immediately
        $type.select2({
            minimumResultsForSearch: 0,
            placeholder: '-- Select Target Audience --',
            width: '100%'
        });

        // Lazy init: only initialize Select2 on role/user AFTER container is visible
        function initRoleSelect2() {
            if (!roleReady) {
                $role.select2({
                    placeholder: '-- Search Role --',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $roleBox
                });
                roleReady = true;
            }
        }

        function initUserSelect2() {
            if (!userReady) {
                $user.select2({
                    placeholder: '-- Search User --',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $userBox
                });
                userReady = true;
            }
        }

        // Show/hide role or user picker based on target type
        function syncTarget() {
            var value = $type.val();

            if (value === 'role') {
                $userBox.slideUp(150);
                $roleBox.slideDown(200, function () { initRoleSelect2(); });
            } else if (value === 'user') {
                $roleBox.slideUp(150);
                $userBox.slideDown(200, function () { initUserSelect2(); });
            } else {
                $roleBox.slideUp(150);
                $userBox.slideUp(150);
            }

            $role.prop('required', value === 'role');
            $user.prop('required', value === 'user');
            $targetId.val(
                value === 'role' ? $role.val() :
                value === 'user' ? $user.val() : ''
            );
        }

        // Events
        $type.on('change.select2 select2:select', syncTarget);

        $role.on('change select2:select', function () {
            $targetId.val($role.val());
        });

        $user.on('change select2:select', function () {
            $targetId.val($user.val());
        });

        // Form submit guard
        $('#form-send-blast').on('submit', function (e) {
            var value = $type.val();
            if (value === 'role') $targetId.val($role.val());
            if (value === 'user') $targetId.val($user.val());

            if ((value === 'role' && !$role.val()) || (value === 'user' && !$user.val())) {
                e.preventDefault();
                Swal.fire('Target required', 'Please select a specific role or user first.', 'warning');
            }
        });
    });
})(window.jQuery);
</script>
@endsection

