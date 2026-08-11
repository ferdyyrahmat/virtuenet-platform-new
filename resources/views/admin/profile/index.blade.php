@extends('layouts.vertical', ['title' => __('messages.my_account')])

@section('css')
<style>
    .avatar-wrapper {
        position: relative;
        display: inline-block;
        width: 110px;
        height: 110px;
        flex-shrink: 0;
    }
    .profile-avatar-img {
        width: 110px !important;
        height: 110px !important;
        object-fit: cover !important;
        object-position: center !important;
        border-radius: 50% !important;
        aspect-ratio: 1 / 1 !important;
    }
    .avatar-overlay {
        position: absolute;
        bottom: 0;
        right: 0;
        background: #008080;
        color: #fff;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 2px solid #fff;
        transition: all 0.2s ease;
    }
    .avatar-overlay:hover {
        transform: scale(1.1);
        background: #005f5f;
    }
    .fs-11 {
        font-size: 0.75rem;
    }
    .fs-13 {
        font-size: 0.85rem;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
        <div class="flex-grow-1">
            <h4 class="fs-18 fw-semibold m-0">{{ __('messages.my_account') }}</h4>
        </div>

        <div class="text-end">
            <ol class="breadcrumb m-0 py-0">
                <li class="breadcrumb-item"><a href="{{ route('root') }}">{{ __('messages.dashboard') }}</a></li>
                <li class="breadcrumb-item active">{{ __('messages.my_account') }}</li>
            </ol>
        </div>
    </div>

    <!-- Profile Header Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 mb-0">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center flex-column flex-md-row gap-4">
                        <div class="avatar-wrapper">
                            <img src="{{ $user->avatar_url }}" id="profile-avatar-preview" class="profile-avatar-img img-thumbnail shadow" alt="user-avatar">
                            <label for="avatar-file-input" class="avatar-overlay" title="Upload New Avatar">
                                <i class="mdi mdi-camera fs-16"></i>
                            </label>
                        </div>
                        <div class="text-center text-md-start flex-grow-1">
                            <h3 class="fw-bold text-dark mb-1 d-flex align-items-center justify-content-center justify-content-md-start gap-2" id="profile-name-header">
                                {{ $user->name }}
                                @if($user->lark_open_id)
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11 font-sans" title="Signed in with Lark SSO">
                                        <i class="mdi mdi-account-key-outline me-1"></i>Lark
                                    </span>
                                @endif
                            </h3>
                            <p class="text-muted fs-14 mb-2" id="profile-email-header"><i class="mdi mdi-email-outline me-1"></i>{{ $user->email }}</p>
                            
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-center justify-content-md-start mb-2">
                                <span class="badge bg-soft-primary text-primary px-3 py-1 fs-12 fw-semibold" id="profile-title-header">
                                    <i class="mdi mdi-briefcase-outline me-1"></i>{{ $user->title ?? 'Team Member' }}
                                </span>
                                @forelse($user->roles as $role)
                                    <span class="badge bg-light text-primary px-2 py-1 fs-11 border border-primary-subtle">
                                        <i class="mdi mdi-shield-check-outline me-1"></i>{{ $role->name }}
                                    </span>
                                @empty
                                    <span class="badge bg-light text-muted fs-11">No Role</span>
                                @endforelse
                            </div>

                            <div class="text-muted fs-12">
                                <i class="mdi mdi-calendar-clock me-1"></i>Member since {{ $user->created_at ? $user->created_at->format('F Y') : '-' }}
                            </div>
                        </div>

                        <div class="d-flex gap-3 text-center border-start-md ps-md-4">
                            <div>
                                <h4 class="fw-bold mb-0 text-primary">{{ $user->roles->count() }}</h4>
                                <span class="text-muted fs-12">Roles</span>
                            </div>
                            <div class="border-start pe-1 ps-3">
                                <h4 class="fw-bold mb-0 text-info">{{ $groupedPermissions->flatten()->count() }}</h4>
                                <span class="text-muted fs-12">Granted Permissions</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs & Content -->
    <div class="row">
        <div class="col-12 col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-body pt-0">
                    <ul class="nav nav-underline border-bottom pt-2" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active p-2" data-bs-toggle="tab" href="#tab-personal" role="tab">
                                {{ __('messages.personal_details') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link p-2" data-bs-toggle="tab" href="#tab-security" role="tab">
                                {{ __('messages.security_password') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link p-2" data-bs-toggle="tab" href="#tab-permissions" role="tab">
                                {{ __('messages.my_permissions') }}
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content text-muted pt-3">
                        <!-- TAB 1: Personal Details -->
                        <div class="tab-pane show active fade" id="tab-personal" role="tabpanel">
                            <form id="profile-info-form" action="{{ route('v1.profile.update-info') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                
                                <input type="file" id="avatar-file-input" name="avatar" class="d-none" accept="image/*">

                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="name" class="form-label fw-medium">{{ __('messages.full_name') }}</label>
                                        <input type="text" class="form-control" id="name" name="name" value="{{ $user->name }}" required placeholder="Your full name">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label fw-medium">{{ __('messages.email_address') }}</label>
                                        <input type="email" class="form-control" id="email" name="email" value="{{ $user->email }}" required placeholder="Your email address">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="phone" class="form-label fw-medium">{{ __('messages.phone_number') }}</label>
                                        <input type="text" class="form-control" id="phone" name="phone" value="{{ $user->phone }}" placeholder="e.g. +62 812 3456 7890">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="title" class="form-label fw-medium">{{ __('messages.job_title') }}</label>
                                        <input type="text" class="form-control" id="title" name="title" value="{{ $user->title }}" placeholder="e.g. Senior Developer, System Admin">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="bio" class="form-label fw-medium">{{ __('messages.bio') }}</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Write a short summary about your role or background">{{ $user->bio }}</textarea>
                                </div>

                                <div class="text-end">
                                    <button type="submit" id="btn-save-info" class="btn btn-primary">
                                        <i class="mdi mdi-content-save-outline me-1"></i>{{ __('messages.save_changes') }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- TAB 2: Security & Password -->
                        <div class="tab-pane fade" id="tab-security" role="tabpanel">
                            <div class="row g-4">
                                <!-- LEFT COLUMN: Password Change Form -->
                                <div class="col-lg-6">
                                    <div class="card border border-light-subtle shadow-sm h-100 mb-0">
                                        <div class="card-header bg-body-tertiary py-3 border-bottom">
                                            <h5 class="card-title mb-0 fs-15 fw-bold text-body">
                                                <i class="mdi mdi-lock-reset text-primary me-1"></i>Change Password
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <form id="profile-password-form" action="{{ route('v1.profile.update-password') }}" method="POST">
                                                @csrf
                                                <div class="mb-3">
                                                    <label for="current_password" class="form-label fw-medium">Current Password</label>
                                                    <input type="password" class="form-control" id="current_password" name="current_password" required placeholder="Enter current password">
                                                </div>

                                                <div class="mb-3">
                                                    <label for="password" class="form-label fw-medium">New Password</label>
                                                    <input type="password" class="form-control" id="password" name="password" required placeholder="Minimum 8 characters">
                                                </div>

                                                <div class="mb-4">
                                                    <label for="password_confirmation" class="form-label fw-medium">Confirm New Password</label>
                                                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required placeholder="Re-enter new password">
                                                </div>

                                                <div class="text-end">
                                                    <button type="submit" id="btn-save-password" class="btn btn-primary px-4">
                                                        <i class="mdi mdi-key-change me-1"></i>Update Password
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- RIGHT COLUMN: 2FA & API Tokens -->
                                <div class="col-lg-6">
                                    <!-- 2FA Card -->
                                    <div class="card border border-light-subtle shadow-sm mb-3">
                                        <div class="card-header bg-body-tertiary py-3 border-bottom d-flex align-items-center justify-content-between">
                                            <h5 class="card-title mb-0 fs-15 fw-bold text-body">
                                                <i class="mdi mdi-shield-key-outline text-primary me-1"></i>Two-Factor Authentication (2FA)
                                            </h5>
                                            <div>
                                                @if(auth()->user()->hasEnabledTwoFactorAuthentication())
                                                    <span class="badge bg-success-subtle text-success border border-success px-2 py-1 fs-11 me-1"><i class="mdi mdi-check-circle me-1"></i>Active</span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1 fs-11 me-1">Disabled</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted fs-13 mb-3">Add an extra layer of security to your account using Google Authenticator or Authy app.</p>
                                            <div class="text-end">
                                                @if(auth()->user()->hasEnabledTwoFactorAuthentication())
                                                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-disable-2fa-modal">
                                                        <i class="mdi mdi-shield-off-outline me-1"></i>Disable 2FA
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-primary btn-sm" id="btn-setup-2fa">
                                                        <i class="mdi mdi-qrcode-scan me-1"></i>Enable 2FA
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sanctum API Tokens Card -->
                                    <div class="card border border-light-subtle shadow-sm mb-0">
                                        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-2 border-bottom">
                                            <h5 class="card-title mb-0 fs-15 fw-bold text-body">
                                                <i class="mdi mdi-key-link text-primary me-1"></i>Personal API Access Tokens
                                            </h5>
                                            <button type="button" class="btn btn-primary btn-xs" data-bs-toggle="modal" data-bs-target="#modal-create-token">
                                                <i class="mdi mdi-plus me-1"></i>Create Token
                                            </button>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover table-sm align-middle mb-0 fs-13" id="table-api-tokens">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="ps-3">Token Name</th>
                                                            <th>Created At</th>
                                                            <th class="text-end pe-3">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse(auth()->user()->tokens as $token)
                                                            <tr id="token-row-{{ $token->id }}">
                                                                <td class="ps-3 fw-semibold text-dark">{{ $token->name }}</td>
                                                                <td class="text-muted">{{ $token->created_at->format('Y-m-d H:i') }}</td>
                                                                <td class="text-end pe-3">
                                                                    <button type="button" class="btn btn-outline-danger btn-xs" onclick="revokeApiToken({{ $token->id }})">Revoke</button>
                                                                </td>
                                                            </tr>
                                                        @empty
                                                            <tr id="no-tokens-row">
                                                                <td colspan="3" class="text-center text-muted py-4 fs-13">
                                                                    <i class="mdi mdi-key-outline fs-24 text-muted d-block mb-1"></i>
                                                                    No active API tokens found.
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

                        <!-- TAB 3: Permissions Matrix -->
                        <div class="tab-pane fade" id="tab-permissions" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-12">
                                    <h5 class="fw-semibold mb-1">Granted Access Matrix</h5>
                                    <p class="text-muted fs-13">Below are the active permissions granted to your account through its assigned roles.</p>
                                </div>
                            </div>

                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                                @forelse($groupedPermissions as $group => $perms)
                                    <div class="col">
                                        <div class="card h-100 border border-light-subtle shadow-none">
                                            <div class="card-header bg-body-tertiary py-2">
                                                <h6 class="m-0 fw-semibold text-primary"><i class="mdi mdi-folder-lock-outline me-1"></i>{{ $group }}</h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <ul class="list-group list-group-flush">
                                                    @foreach($perms as $p)
                                                        <li class="list-group-item px-0 py-2 border-0 d-flex justify-content-between align-items-center bg-transparent">
                                                            <div>
                                                                <span class="fw-semibold text-body fs-13">{{ $p->name }}</span>
                                                        <span class="text-muted d-block fs-11">Permission name · guard_name: web</span>
                                                            </div>
                                                            <span class="badge bg-success-subtle text-success fs-11"><i class="mdi mdi-check-circle-outline me-1"></i>Allowed</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12 text-center py-4">
                                        <i class="mdi mdi-shield-alert-outline text-muted fs-36"></i>
                                        <p class="text-muted fs-14 mt-2">No permissions assigned to your account.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-semibold mb-1">Notification Inbox</h5>
                            <p class="text-muted fs-13 mb-0">All activity logs and system notifications sent to your account.</p>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-clear-profile-noti">
                            <i class="mdi mdi-delete-sweep-outline me-1"></i>Clear All
                        </button>
                    </div>

                    <div class="list-group list-group-flush border rounded-2 overflow-hidden" id="profile-noti-container">
                        @forelse($notifications as $noti)
                            <div class="list-group-item p-3 {{ $noti->is_read ? '' : 'bg-body-tertiary' }} border-bottom position-relative noti-profile-item" id="noti-item-{{ $noti->id }}">
                                <div class="d-flex align-items-start gap-3 pe-4">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20 p-2">
                                            <i class="mdi {{ $noti->icon ?? 'mdi-bell-outline' }}"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <h6 class="mb-0 fw-semibold text-body fs-14">
                                                <a href="{{ $noti->url ?? 'javascript:void(0);' }}" onclick="markNotificationRead({{ $noti->id }}, '{{ $noti->url }}')" class="text-body">
                                                    {{ $noti->title }}
                                                </a>
                                                @if(!$noti->is_read)
                                                    <span class="badge bg-danger rounded-circle p-1 ms-1" style="width: 8px; height: 8px;"> </span>
                                                @endif
                                            </h6>
                                            <span class="text-muted fs-12"><i class="mdi mdi-clock-outline me-1"></i>{{ $noti->created_at ? $noti->created_at->diffForHumans() : 'Just now' }}</span>
                                        </div>
                                        <p class="text-muted mb-0 fs-13">{{ $noti->message }}</p>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm text-muted position-absolute top-0 end-0 me-2 mt-2 border-0" onclick="deleteSingleNotification(event, {{ $noti->id }})" title="Remove notification" style="background: transparent;">
                                    <i class="mdi mdi-close fs-16"></i>
                                </button>
                            </div>
                        @empty
                            <div class="text-center py-5">
                                <i class="mdi mdi-bell-off-outline text-muted fs-36"></i>
                                <p class="text-muted fs-14 mt-2 mb-0">No notifications found in your inbox.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script-bottom')
<script>
    $(document).ready(function() {
        // Preview selected avatar image
        $('#avatar-file-input').on('change', function() {
            var file = this.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#profile-avatar-preview').attr('src', e.target.result);
                }
                reader.readAsDataURL(file);
            }
        });

        // Submit Personal Details form via AJAX
        $('#profile-info-form').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            var btn = $('#btn-save-info');

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    btn.prop('disabled', false).html('<i class="mdi mdi-content-save-outline me-1"></i>Save Changes');
                    if (response.success) {
                        Swal.fire('Saved!', response.message, 'success');
                        if (response.user) {
                            $('#profile-name-header').text(response.user.name);
                            $('#profile-email-header').html('<i class="mdi mdi-email-outline me-1"></i>' + response.user.email);
                            $('#profile-title-header').html('<i class="mdi mdi-briefcase-outline me-1"></i>' + response.user.title);
                            if (response.user.avatar_url) {
                                $('#profile-avatar-preview').attr('src', response.user.avatar_url);
                            }
                        }
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html('<i class="mdi mdi-content-save-outline me-1"></i>Save Changes');
                    var msg = xhr.responseJSON?.message || 'Failed to update profile information.';
                    Swal.fire('Error!', msg, 'error');
                }
            });
        });

        // Submit Security Password form via AJAX
        $('#profile-password-form').off('submit').on('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            var form = $(this);
            var btn = $('#btn-save-password');

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Updating...');

            var formData = form.serialize();

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: formData,
                success: function(response) {
                    btn.prop('disabled', false).html('<i class="mdi mdi-key-change me-1"></i>Update Password');
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Updated!', text: response.message });
                        form[0].reset();
                    } else {
                        Swal.fire({ icon: 'warning', title: 'Failed!', text: response.message });
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html('<i class="mdi mdi-key-change me-1"></i>Update Password');
                    var json = xhr.responseJSON;
                    var msg = (json && json.errors) 
                        ? Object.values(json.errors).flat().join('\n') 
                        : (json?.message || 'Failed to update password.');
                    Swal.fire({ icon: 'error', title: 'Error!', text: msg });
                }
            });
        });

        // Clear All notifications button inside Profile Notifications tab
        $('#btn-clear-profile-noti').on('click', function(e) {
            e.preventDefault();
            $.ajax({
                url: "{{ route('notifications.bell.clear') }}",
                type: "POST",
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function() {
                    $('#profile-noti-container').html('<div class="text-center py-5"><i class="mdi mdi-bell-off-outline text-muted fs-36"></i><p class="text-muted fs-14 mt-2 mb-0">No notifications found in your inbox.</p></div>');
                    $('#profile-noti-badge').hide();
                    if (typeof fetchNotifications === 'function') {
                        fetchNotifications();
                    }
                }
            });
        });

        // Check hash in URL to switch active tab automatically (e.g. #tab-notifications)
        if (window.location.hash) {
            var activeTab = $('a[href="' + window.location.hash + '"]');
            if (activeTab.length) {
                activeTab.tab('show');
            }
        }

        // Enable 2FA Setup Button Click
        $('#btn-setup-2fa').on('click', function() {
            var btn = $(this);
            Swal.fire({
                title: 'Confirm your password',
                input: 'password',
                showCancelButton: true,
                confirmButtonText: 'Continue',
                showLoaderOnConfirm: true,
                preConfirm: (password) => $.ajax({
                    url: "{{ url('/user/confirm-password') }}",
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    data: { password: password }
                }).catch(error => Swal.showValidationMessage(error.responseJSON?.message || 'Password is incorrect.'))
            }).then(function(result) {
                if (!result.isConfirmed) return;
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Generating...');
                $.ajax({
                    url: "{{ url('/user/two-factor-authentication') }}",
                    type: 'POST',
                    dataType: 'json',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    success: function() {
                        $.when(
                            $.getJSON("{{ url('/user/two-factor-qr-code') }}"),
                            $.getJSON("{{ url('/user/two-factor-recovery-codes') }}"),
                            $.getJSON("{{ url('/user/two-factor-secret-key') }}")
                        ).done(function(qr, codes, secret) {
                            $('#container-2fa-qr').html(qr[0].svg);
                            $('#text-2fa-secret').text(secret[0].secretKey);
                            $('#container-2fa-recovery').html(codes[0].map(function(code) {
                                return '<div class="col-6"><code class="fs-12 text-dark">' + code + '</code></div>';
                            }).join(''));
                            new bootstrap.Modal(document.getElementById('modal-setup-2fa')).show();
                        });
                    },
                    error: function(xhr) { Swal.fire('Error!', xhr.responseJSON?.message || 'Failed to generate 2FA setup.', 'error'); },
                    complete: function() { btn.prop('disabled', false).html('<i class="mdi mdi-qrcode-scan me-1"></i>Enable 2FA'); }
                });
            });
        });

        // Confirm 2FA Code Form Submit
        $('#form-confirm-2fa').on('submit', function(e) {
            e.preventDefault();
            var btn = $('#btn-confirm-2fa');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Verifying...');

            $.ajax({
                url: "{{ url('/user/confirmed-two-factor-authentication') }}",
                type: "POST",
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                data: $(this).serialize(),
                success: function(res) {
                    btn.prop('disabled', false).text('Activate 2FA');
                    bootstrap.Modal.getInstance(document.getElementById('modal-setup-2fa')).hide();
                    Swal.fire('Activated!', 'Two-factor authentication is now active.', 'success').then(() => window.location.reload());
                },
                error: function(xhr) {
                    btn.prop('disabled', false).text('Activate 2FA');
                    var msg = xhr.responseJSON?.message || 'Invalid code entered.';
                    Swal.fire('Failed!', msg, 'error');
                }
            });
        });

        // Disable 2FA Modal Trigger
        $('#btn-disable-2fa-modal').on('click', function() {
            Swal.fire({
                title: 'Disable Two-Factor Authentication?',
                text: 'Please enter your current account password to disable 2FA:',
                input: 'password',
                inputAttributes: { autocapitalize: 'off', required: 'required' },
                showCancelButton: true,
                confirmButtonText: 'Disable 2FA',
                confirmButtonColor: '#d33',
                showLoaderOnConfirm: true,
                preConfirm: (password) => {
                    return $.ajax({
                        url: "{{ url('/user/two-factor-authentication') }}",
                        type: "DELETE",
                        dataType: 'json',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    }).catch(error => {
                        Swal.showValidationMessage(error.responseJSON?.message || 'Request failed');
                    });
                }
            }).then((result) => {
                if (result.isConfirmed && result.value?.success) {
                    Swal.fire('Disabled!', result.value.message, 'success').then(() => window.location.reload());
                }
            });
        });

        // Create Sanctum API Token
        $('#form-create-token').on('submit', function(e) {
            e.preventDefault();
            var btn = $('#btn-save-token');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Creating...');

            $.ajax({
                url: "{{ route('v1.profile.tokens.store') }}",
                type: "POST",
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                data: $(this).serialize(),
                success: function(res) {
                    btn.prop('disabled', false).text('Create Token');
                    if (res.success) {
                        bootstrap.Modal.getInstance(document.getElementById('modal-create-token')).hide();
                        $('#no-tokens-row').remove();

                        var newRow = '<tr id="token-row-' + res.token_id + '">' +
                            '<td class="fw-semibold">' + res.name + '</td>' +
                            '<td class="text-muted">' + res.created_at + '</td>' +
                            '<td class="text-end"><button type="button" class="btn btn-outline-danger btn-xs" onclick="revokeApiToken(' + res.token_id + ')">Revoke</button></td>' +
                            '</tr>';
                        $('#table-api-tokens tbody').prepend(newRow);

                        Swal.fire({
                            icon: 'success',
                            title: 'API Token Created!',
                            html: '<p>Please copy your secret key now. It will not be shown again:</p><input type="text" class="form-control text-center font-monospace" value="' + res.token_key + '" readonly onclick="this.select(); document.execCommand(\'copy\'); Swal.fire(\'Copied!\', \'Token copied to clipboard.\', \'success\');">'
                        });
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false).text('Create Token');
                    Swal.fire('Error!', xhr.responseJSON?.message || 'Failed to create token.', 'error');
                }
            });
        });

        window.revokeApiToken = function(id) {
            Swal.fire({
                title: 'Revoke API Token?',
                text: 'Applications using this API token will immediately lose access.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, Revoke!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "/v1/profile/tokens/" + id,
                        type: "DELETE",
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        success: function(res) {
                            $('#token-row-' + id).fadeOut(300, function() { $(this).remove(); });
                            Swal.fire('Revoked!', res.message, 'success');
                        }
                    });
                }
            });
        };
    });
</script>

<!-- MODAL: Setup 2FA -->
<div class="modal fade" id="modal-setup-2fa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="mdi mdi-qrcode-scan text-primary me-1"></i>Setup Two-Factor Authentication</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted fs-13 mb-3">Scan this QR Code with your Google Authenticator or Authy app on your mobile phone.</p>

                <div class="p-3 bg-light rounded-3 d-inline-block mb-3 border" id="container-2fa-qr">
                    <!-- QR Code SVG injected via JS -->
                </div>

                <p class="text-muted fs-12 mb-1">Secret Key (if manual entry needed):</p>
                <div class="badge bg-secondary-subtle text-dark fs-14 font-monospace px-3 py-2 mb-3" id="text-2fa-secret"></div>

                <div class="text-start bg-light p-3 rounded-3 mb-3 border">
                    <h6 class="fw-bold fs-12 mb-2 text-danger"><i class="mdi mdi-alert-circle-outline me-1"></i>Emergency Recovery Codes</h6>
                    <p class="text-muted fs-11 mb-2">Keep these codes in a safe place. If you lose your phone, you can use one of these to log in:</p>
                    <div class="row g-1" id="container-2fa-recovery"></div>
                </div>

                <form id="form-confirm-2fa">
                    <div class="mb-3 text-start">
                        <label for="input-2fa-code" class="form-label fw-semibold fs-13">Enter 6-Digit Verification Code</label>
                        <input type="text" class="form-control text-center font-monospace fs-18" id="input-2fa-code" name="code" placeholder="000000" maxlength="6" required autocomplete="off">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" id="btn-confirm-2fa">Activate 2FA</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Create Sanctum API Token -->
<div class="modal fade" id="modal-create-token" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="mdi mdi-key-plus text-primary me-1"></i>Create Personal API Token</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-create-token">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="token_name" class="form-label fw-semibold">Token Name / Description</label>
                        <input type="text" class="form-control" id="token_name" name="token_name" required placeholder="e.g. Mobile App Token, Testing Script">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn-save-token">Create Token</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
