@extends('layouts.vertical', ['title' => 'Developer Dashboard'])

@section('content')
<div class="container-fluid">
    <div class="py-3 d-flex justify-content-between align-items-center">
        <div><h4 class="fs-18 fw-semibold mb-1">Developer workspace</h4><p class="text-muted mb-0">Welcome back, {{ $user->name }}. Here is your delivery queue.</p></div>
        <a href="{{ route('admin.tickets.index') }}" class="btn btn-primary"><i class="mdi mdi-lifebuoy me-1"></i>Open ticket queue</a>
    </div>
    <div class="row g-3 mb-4">
        @foreach ([['open','Open tickets','mdi-inbox-arrow-down','primary'],['in_progress','In progress','mdi-progress-clock','warning'],['waiting','Waiting for user','mdi-account-clock-outline','info'],['resolved','Resolved','mdi-check-circle-outline','success']] as $card)
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between"><div><span class="text-muted fs-12">{{ $card[1] }}</span><h3 class="mt-2 mb-0">{{ $stats[$card[0]] }}</h3></div><div class="avatar-sm rounded-circle bg-{{ $card[3] }}-subtle text-{{ $card[3] }} d-flex align-items-center justify-content-center"><i class="mdi {{ $card[2] }} fs-22"></i></div></div></div></div></div>
        @endforeach
    </div>
    <div class="row g-3">
        <div class="col-xl-8"><div class="card border-0 shadow-sm"><div class="card-header bg-transparent d-flex justify-content-between"><h5 class="mb-0">My assigned tickets</h5><a href="{{ route('admin.tickets.index') }}">View all</a></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><tbody>@forelse($recentTickets as $ticket)<tr><td class="ps-3"><a class="fw-semibold" href="{{ route('admin.tickets.show', $ticket->id) }}">{{ $ticket->ticket_code }}</a><div class="text-muted fs-12">{{ $ticket->subject }}</div></td><td><span class="badge bg-light text-dark">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</span></td><td class="text-muted fs-12">{{ $ticket->created_at->diffForHumans() }}</td></tr>@empty<tr><td class="p-4 text-center text-muted">No tickets assigned yet.</td></tr>@endforelse</tbody></table></div></div></div></div>
        <div class="col-xl-4"><div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0">Platform monitoring</h5></div><div class="card-body"><p class="text-muted">Pulse is the single source of truth for application and infrastructure metrics.</p><a href="{{ url('/pulse') }}" class="btn btn-outline-primary"><i class="mdi mdi-chart-line me-1"></i>Open Laravel Pulse</a></div></div></div>
    </div>
</div>
@endsection
