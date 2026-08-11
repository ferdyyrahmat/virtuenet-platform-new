@extends('layouts.auth', ['title' => 'Login'])

@section('content')

<div class="col-xl-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card p-3 mb-0">
                <div class="card-body">

                    <div class="mb-0 border-0 p-md-5 p-lg-0 p-4">
                        <div class="mb-4 p-0 text-center">
                            <a href="{{ route('root') }}" class="auth-logo">
                                <img src="/images/logo-dark.png" alt="logo-dark" class="mx-auto" height="28" />
                            </a>
                        </div>

                        <div class="auth-title-section mb-3 text-center">
                            <h3 class="text-dark fs-20 fw-medium mb-2">Welcome back</h3>
                            <p class="text-dark text-capitalize fs-14 mb-0">Sign in to continue to silve.</p>
                        </div>

                        <div class="d-grid mb-3">
                            <a href="{{ route('lark.redirect') }}" class="btn text-dark border fw-normal d-flex align-items-center justify-content-center py-2">
                                <i class="mdi mdi-account-key-outline fs-20 text-dark me-2"></i>
                                <span>Continue with Lark</span>
                            </a>
                        </div>

                        <div class="saprator my-4"><span>or continue with email</span></div>

                        <div class="pt-0">
                            <form method="POST" action="{{ route('login.store') }}" class="my-4">
                                
                                @csrf
                                @if (session('error'))
                                    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                                        <i class="mdi mdi-alert-circle-outline me-1"></i>{{ session('error') }}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                @endif

                                @if (session('status'))
                                    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                                        <i class="mdi mdi-check-circle-outline me-1"></i>{{ session('status') }}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                @endif

                                @if (sizeof($errors) > 0)
                                @foreach ($errors->all() as $error)
                                <p class="text-danger mb-3">{{ $error }}</p>
                                @endforeach
                                @endif
                                <div class="form-group mb-3">
                                    <label for="emailaddress" class="form-label">Email address</label>
                                    <input class="form-control" type="email" name="email" id="emailaddress" required="" placeholder="Enter your email">
                                </div>

                                <div class="form-group mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input class="form-control" type="password" required="" id="password" name="password" placeholder="Enter your password">
                                </div>

                                <div class="form-group d-flex mb-3">
                                    <div class="col-sm-6">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="checkbox-signin" name="remember" value="1" checked>
                                            <label class="form-check-label" for="checkbox-signin">Remember me</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 text-end">
                                        <a class='text-muted fs-14' href='{{ route('password.request') }}'>Forgot password?</a>
                                    </div>
                                </div>

                                <div class="form-group mb-0 row">
                                    <div class="col-12">
                                        <div class="d-grid">
                                            <button class="btn btn-primary" type="submit"> Log In </button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <div class="text-center text-muted mb-4">
                                <p class="mb-0">Don't have an account ?<a class='text-primary ms-2 fw-medium' href='{{ route('register') }}'>Sign up</a></p>
                            </div>

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
