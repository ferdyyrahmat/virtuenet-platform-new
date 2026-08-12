@extends('layouts.vertical', ['title' => 'New Service Request'])

@section('content')
<div class="container-fluid platform-page">
    <div class="platform-heading">
        <div><span class="eyebrow">Service catalog</span><h4>New service request</h4><p>One guided form, a clear approval trail, and delivery updates in one place.</p></div>
        <a href="{{ route('v1.requests.index') }}" class="btn btn-light btn-sm"><i class="mdi mdi-arrow-left me-1"></i>My requests</a>
    </div>
    <div class="card platform-card"><div class="card-body p-4">@include('requests._form')</div></div>
</div>
@endsection
