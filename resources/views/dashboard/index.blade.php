@extends('layouts.vertical', ['title' => __('messages.dashboard')])

@section('content')
<div class="container-fluid">
    <div class="py-3">
        <h4 class="fs-18 fw-semibold mb-1">{{ __('messages.dashboard') }}</h4>
        <p class="text-muted mb-0">Platform metrics and application monitoring are available in Laravel Pulse.</p>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center justify-content-between gap-3">
            <div>
                <h5 class="mb-1">Laravel Pulse</h5>
                <p class="text-muted mb-0">View requests, slow jobs, exceptions, queues, and system health from one monitoring surface.</p>
            </div>
            <a href="{{ url('/pulse') }}" class="btn btn-primary flex-shrink-0"><i class="mdi mdi-chart-line me-1"></i>Open Pulse</a>
        </div>
    </div>
</div>
@endsection
