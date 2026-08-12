@extends('layouts.vertical', ['title' => 'My Requests'])

@section('content')
<div class="container-fluid platform-page">
    <div class="platform-heading">
        <div><span class="eyebrow">My workspace</span><h4>Service requests</h4><p>Track approvals, delivery, and outcomes without chasing updates.</p></div>
        <a href="{{ route('v1.requests.create') }}" class="btn btn-primary btn-sm"><i class="mdi mdi-plus me-1"></i>New request</a>
    </div>
    <div class="service-catalog mb-4">
        @foreach(\App\Enums\ServiceRequestType::cases() as $requestType)
            <a href="{{ $requestType === \App\Enums\ServiceRequestType::Support ? route('v1.tickets.index') : route('v1.requests.create', ['type' => $requestType->value]) }}" class="service-catalog-item"><i class="mdi {{ $requestType->icon() }}"></i><span>{{ $requestType->label() }}</span></a>
        @endforeach
    </div>
    <div class="card platform-card">
        <div class="card-header platform-card-header"><strong>Request history</strong><form method="GET" class="native-submit-form d-flex flex-wrap gap-2"><select name="type" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All services</option>@foreach(\App\Enums\ServiceRequestType::cases() as $t)<option value="{{ $t->value }}" @selected(request('type')===$t->value)>{{ $t->label() }}</option>@endforeach</select><select name="approval_status" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Any approval</option>@foreach(\App\Enums\RequestApprovalStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('approval_status')===$s->value)>{{ $s->label() }}</option>@endforeach</select><select name="fulfilment_status" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Any fulfilment</option>@foreach(\App\Enums\RequestFulfilmentStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('fulfilment_status')===$s->value)>{{ $s->label() }}</option>@endforeach</select></form></div>
        <div class="table-responsive"><table class="table align-middle mb-0 platform-table"><thead><tr><th>Request</th><th>Service</th><th>Approval</th><th>Fulfilment</th><th>Updated</th><th></th></tr></thead><tbody>
            @forelse($requests as $item)<tr><td><strong>{{ $item->title }}</strong><small>{{ $item->code }}</small></td><td>{{ $item->type->label() }}</td><td><span class="badge bg-{{ $item->approval_status->color() }}-subtle text-{{ $item->approval_status->color() }}">{{ $item->approval_status->label() }}</span></td><td><span class="badge bg-{{ $item->fulfilment_status->color() }}-subtle text-{{ $item->fulfilment_status->color() }}">{{ $item->fulfilment_status->label() }}</span></td><td>{{ $item->updated_at->diffForHumans() }}</td><td class="text-end"><a href="{{ route('v1.requests.show', $item) }}" class="btn btn-light btn-sm">Open</a></td></tr>
            @empty<tr><td colspan="6"><div class="empty-state"><i class="mdi mdi-clipboard-text-outline"></i><strong>No requests yet</strong><span>Choose a service above to get started.</span></div></td></tr>@endforelse
        </tbody></table></div>
        @if($requests->hasPages())<div class="card-footer">{{ $requests->links() }}</div>@endif
    </div>
</div>
@endsection
