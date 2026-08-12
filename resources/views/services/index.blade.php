@extends('layouts.vertical', ['title' => 'Services'])

@section('content')
<div class="container-fluid platform-page">
    <div class="platform-heading">
        <div><span class="eyebrow">Service registry</span><h4>{{ $catalog ? 'Live service catalog' : 'My department services' }}</h4><p>{{ $catalog ? 'Internal services currently reachable by employees.' : 'Deployment, ownership, and uptime for your departments.' }}</p></div>
        <div class="d-flex gap-2"><a class="btn btn-sm {{ !$catalog ? 'btn-primary':'btn-light' }}" href="{{ route('v1.services.index') }}">My services</a><a class="btn btn-sm {{ $catalog ? 'btn-primary':'btn-light' }}" href="{{ route('v1.services.index',['scope'=>'catalog']) }}">Live catalog</a></div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([['Visible services',$stats['total'],'mdi-view-grid-outline'],['Online on this page',$stats['online'],'mdi-check-circle-outline'],['Needs attention',$stats['attention'],'mdi-alert-outline']] as [$label,$value,$icon])
            <div class="col-6 col-xl-4"><div class="card platform-card h-100"><div class="card-body p-3 d-flex align-items-center justify-content-between"><div><small class="text-uppercase text-muted fw-semibold">{{ $label }}</small><h4 class="mb-0 mt-1">{{ $value }}</h4></div><span class="service-icon"><i class="mdi {{ $icon }}"></i></span></div></div></div>
        @endforeach
    </div>

    <div class="card platform-card mb-3"><div class="card-body py-3"><form method="GET" class="native-submit-form d-flex flex-wrap gap-2"><input type="hidden" name="scope" value="{{ $catalog ? 'catalog':'' }}"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search service or domain"><select name="department_id" class="form-select form-select-sm"><option value="">All visible departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)request('department_id')===(string)$department->id)>{{ $department->name }}</option>@endforeach</select><button class="btn btn-light btn-sm">Filter</button></form></div></div>

    <div class="row g-3">
        @forelse($applications as $application)
            @php($healthColor=match($application->health_status){'online'=>'success','degraded'=>'warning','offline'=>'danger',default=>'secondary'})
            <div class="col-md-6 col-xl-4"><div class="card platform-card h-100"><div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div class="d-flex gap-3 align-items-center">@if($application->thumbnail_url)<img src="{{ $application->thumbnail_url }}" alt="" class="rounded" width="44" height="44">@else<span class="service-icon"><i class="mdi mdi-application-outline"></i></span>@endif<div><h6 class="mb-1">{{ $application->display_name ?: $application->repo_full_name }}</h6><small class="text-muted">{{ $application->department?->name ?: 'Shared service' }}</small></div></div><span class="badge bg-{{ $healthColor }}-subtle text-{{ $healthColor }}">{{ str($application->health_status)->replace('_',' ')->title() }}</span></div>
                <dl class="row small mb-3"><dt class="col-5 text-muted fw-normal">Owner</dt><dd class="col-7 text-end mb-2">{{ $application->owner?->name ?: 'Unassigned' }}</dd><dt class="col-5 text-muted fw-normal">Cluster</dt><dd class="col-7 text-end mb-2">{{ $application->node?->name ?: 'Unassigned' }}</dd><dt class="col-5 text-muted fw-normal">30-day uptime</dt><dd class="col-7 text-end mb-2">{{ isset($uptime[$application->repo_full_name]) ? number_format($uptime[$application->repo_full_name],2).'%' : 'Collecting data' }}</dd><dt class="col-5 text-muted fw-normal">Last deployed</dt><dd class="col-7 text-end mb-0">{{ $application->deployed_at?->diffForHumans() ?: 'Unknown' }}</dd></dl>
                @if($application->domain)<a href="https://{{ $application->domain }}" target="_blank" rel="noopener" class="btn btn-light btn-sm w-100">Open service <i class="mdi mdi-open-in-new ms-1"></i></a>@else<div class="alert alert-light small mb-0">Internal service; no public domain.</div>@endif
            </div></div></div>
        @empty
            <div class="col-12"><div class="card platform-card"><div class="empty-state py-5"><i class="mdi mdi-server-network-off"></i><strong>No services found</strong><span>{{ $catalog ? 'No live service matches this filter.' : 'No service is assigned to your department yet.' }}</span></div></div></div>
        @endforelse
    </div>
    @if($applications->hasPages())<div class="mt-3">{{ $applications->links() }}</div>@endif
</div>
@endsection
