@extends('layouts.vertical', ['title' => $serviceRequest->code])

@section('content')
<div class="container-fluid platform-page">
    <div class="platform-heading">
        <div><span class="eyebrow">{{ $serviceRequest->type->label() }} · {{ $serviceRequest->code }}</span><h4>{{ $serviceRequest->title }}</h4><p>Submitted {{ $serviceRequest->submitted_at?->format('d M Y, H:i') }} · Last updated {{ $serviceRequest->updated_at->diffForHumans() }}</p></div>
        <span class="status-pill status-{{ $serviceRequest->status->color() }}">{{ $serviceRequest->status->label() }}</span>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card platform-card mb-4"><div class="card-body p-4">
                <h6 class="section-title">Request summary</h6><p class="text-body-secondary mb-4">{{ $serviceRequest->description }}</p>
                <div class="detail-grid">
                    @foreach($serviceRequest->details as $key => $value)
                        <div><small>{{ str($key)->replace('_', ' ')->title() }}</small><strong>{{ is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? 'Yes' : 'No') : ($value ?: '—')) }}</strong></div>
                    @endforeach
                    <div><small>Priority</small><strong>{{ str($serviceRequest->priority)->title() }}</strong></div>
                    <div><small>Needed by</small><strong>{{ $serviceRequest->requested_due_date?->format('d M Y') ?: 'Flexible' }}</strong></div>
                    <div><small>Estimated total</small><strong>{{ $serviceRequest->estimated_budget !== null ? $serviceRequest->currency.' '.number_format((float)$serviceRequest->estimated_budget, 2) : 'Not provided' }}</strong></div>
                    <div><small>Department</small><strong>{{ $serviceRequest->department?->name ?: 'Not assigned' }}</strong></div>
                    <div><small>Approval</small><strong>{{ $serviceRequest->approval_status->label() }}</strong></div>
                    <div><small>Fulfilment</small><strong>{{ $serviceRequest->fulfilment_status->label() }}</strong></div>
                    @if($serviceRequest->parent)<div><small>Parent project</small><strong><a href="{{ route('v1.requests.show', $serviceRequest->parent) }}">{{ $serviceRequest->parent->code }}</a></strong></div>@endif
                </div>
            </div></div>

            @if($serviceRequest->attachments->isNotEmpty())
                <div class="card platform-card mb-4"><div class="card-body p-4"><h6 class="section-title">Supporting files</h6><div class="detail-list">@foreach($serviceRequest->attachments as $attachment)<a class="d-flex justify-content-between align-items-center py-2" href="{{ route('v1.request-attachments.download', $attachment) }}"><span><i class="mdi mdi-paperclip me-2"></i>{{ $attachment->original_name }}</span><small>{{ \Illuminate\Support\Number::fileSize($attachment->size) }}</small></a>@endforeach</div></div></div>
            @endif

            @if($serviceRequest->aiCredential)
                <div class="card platform-card mb-4 border-success"><div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start gap-3"><div><span class="eyebrow text-success">Ready to use</span><h6 class="mb-1">AI access token</h6><p class="text-muted mb-3">Keep this token private. Usage and budget are monitored through the connected gateway.</p></div><a href="{{ route('v1.ai-usage.index') }}" class="btn btn-outline-success btn-sm">View usage</a></div>
                    <div class="credential-box" data-key-delivery>
                        @if(!$serviceRequest->aiCredential->revealed_at && $serviceRequest->aiCredential->reveal_expires_at?->isFuture())
                            <span>This key can be revealed once until {{ $serviceRequest->aiCredential->reveal_expires_at->format('d M Y, H:i') }}.</span>
                            <button type="button" class="btn btn-sm btn-success" data-reveal-key data-url="{{ route('v1.ai-credentials.reveal', $serviceRequest->aiCredential) }}">Reveal once</button>
                        @else
                            <span>Key delivered. Rotate it if a replacement is needed.</span>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3"><span class="badge bg-light text-body">Budget ${{ number_format((float) $serviceRequest->aiCredential->max_budget, 2) }}</span><span class="badge bg-light text-body">{{ $serviceRequest->aiCredential->budget_duration }}</span>@foreach($serviceRequest->aiCredential->models ?? [] as $model)<span class="badge bg-primary-subtle text-primary">{{ $model }}</span>@endforeach</div>
                </div></div>
            @endif

            @if($serviceRequest->status === \App\Enums\ServiceRequestStatus::RevisionRequested)
                <div class="card platform-card mb-4"><div class="card-header platform-card-header"><div><strong>Revision requested</strong><small>Update the request using the reviewer feedback below.</small></div></div><div class="card-body p-4">@include('requests._form')</div></div>
            @endif

            <div class="card platform-card"><div class="card-header platform-card-header"><div><strong>Activity</strong><small>Comments and system updates in chronological order.</small></div></div><div class="card-body p-4">
                <div class="activity-stream">
                    @forelse($serviceRequest->updates->sortByDesc('created_at') as $update)
                        <div class="activity-item"><span class="activity-dot"></span><div><div class="d-flex justify-content-between gap-3"><strong>{{ $update->actor?->name ?? 'System' }}</strong><small>{{ $update->created_at->diffForHumans() }}</small></div><p>{{ $update->message }}</p></div></div>
                    @empty<div class="empty-state compact">No activity yet.</div>@endforelse
                </div>
                @if(!in_array($serviceRequest->status, [\App\Enums\ServiceRequestStatus::Cancelled, \App\Enums\ServiceRequestStatus::Rejected], true))
                    <form method="POST" action="{{ route('v1.requests.comments.store', $serviceRequest) }}" class="mt-3">@csrf<div class="input-group"><input name="message" class="form-control" maxlength="5000" placeholder="Add context or ask a question…" required><button class="btn btn-primary" type="submit">Send</button></div></form>
                @endif
            </div></div>
        </div>
        <div class="col-xl-4">
            <div class="card platform-card mb-4"><div class="card-body p-4"><h6 class="section-title">Request lifecycle</h6><div class="approval-steps"><div class="approval-step is-{{ $serviceRequest->approval_status->value === 'approved' ? 'approved' : 'pending' }}"><span><i class="mdi mdi-clipboard-check-outline"></i></span><div><strong>Decision</strong><small>{{ $serviceRequest->approval_status->label() }}</small></div></div><div class="approval-step is-{{ $serviceRequest->fulfilment_status->value === 'completed' ? 'approved' : 'pending' }}"><span><i class="mdi mdi-progress-wrench"></i></span><div><strong>Fulfilment</strong><small>{{ $serviceRequest->fulfilment_status->label() }}</small></div></div></div>@if($serviceRequest->lark_approval_url)<a href="{{ $serviceRequest->lark_approval_url }}" class="btn btn-light btn-sm w-100 mt-3" target="_blank" rel="noopener">Open in Lark</a>@endif</div></div>
            <div class="card platform-card mb-4"><div class="card-body p-4"><h6 class="section-title">Approval progress</h6>
                <div class="approval-steps">@foreach($serviceRequest->approvals->groupBy('round') as $round => $approvals)<div class="approval-round">Round {{ $round }}</div>@foreach($approvals as $approval)<div class="approval-step is-{{ $approval->status }}"><span><i class="mdi {{ $approval->status === 'approved' ? 'mdi-check' : ($approval->status === 'pending' ? 'mdi-clock-outline' : 'mdi-alert-outline') }}"></i></span><div><strong>{{ $approval->stage }}</strong><small>{{ str($approval->status)->replace('_',' ')->title() }}{{ $approval->approver ? ' by '.$approval->approver->name : '' }}</small>@if($approval->note)<p>{{ $approval->note }}</p>@endif</div></div>@endforeach@endforeach</div>
            </div></div>
            @if($serviceRequest->delivery)
                <div class="card platform-card mb-4"><div class="card-body p-4"><h6 class="section-title">Delivery</h6><div class="detail-list"><div><span>Status</span><strong>{{ str($serviceRequest->delivery->status)->title() }}</strong></div><div><span>Reference</span><strong>{{ $serviceRequest->delivery->reference ?: '—' }}</strong></div>@if($serviceRequest->delivery->access_url)<a href="{{ $serviceRequest->delivery->access_url }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm mt-3 w-100">Open delivery</a>@endif</div></div></div>
            @endif
            @can('cancel', $serviceRequest)<form method="POST" action="{{ route('v1.requests.cancel', $serviceRequest) }}" data-confirm="Cancel this request?">@csrf<button class="btn btn-outline-danger btn-sm w-100" type="submit">Cancel request</button></form>@endcan
        </div>
    </div>
</div>
@endsection
