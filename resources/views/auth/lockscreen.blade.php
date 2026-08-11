@extends('layouts.auth', ['title' => 'Lock Screen'])

@section('content')
<div class="col-md-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card p-3 mb-0 shadow-lg border-0" style="border-radius: 16px;">
                <div class="card-body">

                    <div class="mb-0 border-0 p-md-4 p-3">
                        <div class="mb-4 text-center">
                            <a href="{{ route('root') }}" class="auth-logo">
                                <img src="/images/logo-dark.png" alt="logo-dark" class="mx-auto" height="28"/>
                            </a>
                        </div>
                        
                        <div class="text-center auth-title-section mb-4">
                            <div class="mb-3">
                                <img src="{{ auth()->user()->avatar_url }}" class="rounded-circle img-thumbnail shadow-sm" alt="user-avatar" width="88" height="88" style="object-fit: cover; object-position: center; aspect-ratio: 1 / 1;">
                            </div>
                            <h4 class="text-dark fs-18 fw-semibold mb-1">{{ auth()->user()->name }}</h4>
                            <p class="text-muted fs-13 mb-0">{{ auth()->user()->email }}</p>
                            <span class="badge bg-light text-warning mt-2"><i class="mdi mdi-lock-outline me-1"></i>Session Locked</span>
                        </div>
                    
                        <!-- Error Alert Box -->
                        <div id="lockscreen-alert" class="alert alert-danger alert-dismissible fade show mb-3 @if(!$errors->any()) d-none @endif" role="alert">
                            <i class="mdi mdi-alert-circle-outline me-1"></i>
                            <span id="lockscreen-alert-text">{{ $errors->first() }}</span>
                            <button type="button" class="btn-close" onclick="$('#lockscreen-alert').addClass('d-none');" aria-label="Close"></button>
                        </div>

                        <form id="unlock-form" action="{{ route('lockscreen.unlock') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="password" class="form-label text-dark fw-medium">Password</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required placeholder="Enter your password to unlock" autofocus>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" id="btn-unlock" class="btn btn-primary w-100 py-2 fw-medium me-1">
                                    <i class="mdi mdi-lock-open-outline me-1"></i> Unlock Screen
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted fs-13 mb-0">Not {{ auth()->user()->name }}? <a href="{{ route('logout.get') }}" class="text-primary fw-medium ms-1">Log Out</a></p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<div class="col-xl-7">
    <div class="account-page-bg p-md-5 p-4">
        <div class="text-center">
            <div class="auth-image">
                <img src="/images/auth-images.svg" class="mx-auto img-fluid" alt="images">
            </div>
        </div>
    </div>
</div>
@endsection

@section('script-bottom')
<script>
    $(document).ready(function() {
        $('#unlock-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var btn = $('#btn-unlock');
            var alertBox = $('#lockscreen-alert');
            var alertText = $('#lockscreen-alert-text');
            var passwordInput = $('#password');

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Unlocking...');
            alertBox.addClass('d-none');
            passwordInput.removeClass('is-invalid');

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.redirect || "{{ route('root') }}";
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html('<i class="mdi mdi-lock-open-outline me-1"></i> Unlock Screen');
                    var errorMsg = 'Password wrong. Please try again.';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON.errors && xhr.responseJSON.errors.password) {
                            errorMsg = xhr.responseJSON.errors.password[0];
                        }
                    }
                    alertText.text(errorMsg);
                    alertBox.removeClass('d-none');
                    passwordInput.addClass('is-invalid').focus();
                }
            });
        });
    });
</script>
@endsection
