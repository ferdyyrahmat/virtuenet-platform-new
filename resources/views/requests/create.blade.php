@extends('layouts.vertical', ['title' => 'New Service Request'])

@section('content')
<div class="container-fluid platform-page">
    <div class="platform-heading">
        <div><span class="eyebrow">Service catalog</span><h4>New service request</h4><p>One guided form, a clear approval trail, and delivery updates in one place.</p></div>
        <a href="{{ route('v1.requests.index') }}" class="btn btn-light btn-sm"><i class="mdi mdi-arrow-left me-1"></i>My requests</a>
    </div>
    @if($larkManaged)
        <div class="card platform-card"><div class="card-body p-4 p-lg-5 text-center"><span class="service-icon mx-auto mb-3"><i class="mdi mdi-check-decagram-outline"></i></span><span class="eyebrow">Lark is the approval source</span><h5 class="mt-2">Start this request in Lark Modified</h5><p class="text-body-secondary mx-auto" style="max-width: 620px">The platform will import the approved form, preserve its source snapshot, and show fulfilment progress here. This prevents two competing approval records.</p>@if($larkFormUrl)<a class="btn btn-primary" href="{{ $larkFormUrl }}" target="_blank" rel="noopener"><i class="mdi mdi-open-in-new me-1"></i>Open Lark request form</a>@else<div class="alert alert-warning text-start mb-0">The Lark request form URL has not been configured. Ask an administrator to set <code>LARK_APPROVAL_FORM_URL</code>.</div>@endif</div></div>
    @else
        <div class="card platform-card"><div class="card-body p-4">@include('requests._form')</div></div>
    @endif
</div>
@endsection
