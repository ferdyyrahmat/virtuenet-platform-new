@extends('layouts.base', ['title' => '2FA Authentication'])

@section('content')
<div class="account-pages py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-body p-4">
                        
                        <div class="text-center w-75 m-auto mb-4">
                            <div class="avatar-md mx-auto mb-3">
                                <div class="avatar-title bg-primary-subtle text-primary rounded-circle fs-36 p-3">
                                    <i class="mdi mdi-shield-key-outline"></i>
                                </div>
                            </div>
                            <h4 class="text-dark-50 text-center mt-0 fw-bold">Two-Factor Authentication</h4>
                            <p class="text-muted mb-0">Please enter the 6-digit authentication code from your authenticator app.</p>
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                {{ $errors->first() }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <form action="{{ route('two-factor.login.store') }}" method="POST" id="form-2fa-code" class="auth-json-form">
                            @csrf

                            <div class="mb-3" id="group-code">
                                <label for="code" class="form-label fw-semibold">Authentication Code</label>
                                <input type="text" class="form-control form-control-lg text-center fs-20 tracking-widest" id="code" name="code" placeholder="000000" maxlength="6" autofocus autocomplete="off">
                            </div>

                            <div class="mb-3 d-none" id="group-recovery">
                                <label for="recovery_code" class="form-label fw-semibold">Emergency Recovery Code</label>
                                <input type="text" class="form-control form-control-lg text-center fs-14" id="recovery_code" name="recovery_code" placeholder="xxxx-xxxx-xxxx" autocomplete="off">
                            </div>

                            <div class="d-grid mb-3">
                                <button class="btn btn-primary btn-lg fw-semibold" type="submit">Verify Code</button>
                            </div>

                            <div class="text-center">
                                <button type="button" class="btn btn-link text-muted btn-sm" id="btn-toggle-recovery">
                                    Use an emergency recovery code
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script-bottom')
<script>
    document.getElementById('btn-toggle-recovery').addEventListener('click', function() {
        const groupCode = document.getElementById('group-code');
        const groupRecovery = document.getElementById('group-recovery');
        const inputCode = document.getElementById('code');
        const inputRecovery = document.getElementById('recovery_code');

        if (groupRecovery.classList.contains('d-none')) {
            groupRecovery.classList.remove('d-none');
            groupCode.classList.add('d-none');
            inputCode.value = '';
            inputRecovery.focus();
            this.textContent = 'Use authentication code from app';
        } else {
            groupRecovery.classList.add('d-none');
            groupCode.classList.remove('d-none');
            inputRecovery.value = '';
            inputCode.focus();
            this.textContent = 'Use an emergency recovery code';
        }
    });
</script>
@endsection
