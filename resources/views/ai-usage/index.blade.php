@extends('layouts.vertical', ['title' => 'AI Monitoring'])

@php
    $activeCredentials = $credentials->where('status', 'active');
    $totalBudget = (float) $credentials->sum('max_budget');
    $localSpend = (float) $credentials->sum('current_spend');
    $budgetUsage = $totalBudget > 0 ? min(100, ($localSpend / $totalBudget) * 100) : 0;
@endphp

@section('content')
<div class="container-fluid platform-page ai-monitoring" data-ai-usage-url="{{ $dataUrl }}" data-ai-budget="{{ $totalBudget }}">
    <div class="platform-heading">
        <div><span class="eyebrow">Live LiteLLM gateway data</span><h4>{{ $scopeLabel }}</h4><p>Operational visibility for virtual keys, budget, usage, models, and request traffic.</p></div>
        <div class="d-flex align-items-center gap-2"><select class="form-select form-select-sm w-auto" data-ai-days aria-label="Monitoring period"><option value="7">Last 7 days</option><option value="30" selected>Last 30 days</option><option value="90">Last 90 days</option></select><button type="button" class="btn btn-primary btn-sm" data-ai-refresh><i class="mdi mdi-refresh me-1"></i>Refresh</button></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="metric-card ai-metric"><span class="ai-icon"><i class="mdi mdi-key-outline"></i></span><small>Active tokens</small><strong>{{ $activeCredentials->count() }}</strong><p>Virtual keys currently active</p></div></div>
        <div class="col-sm-6 col-xl-3"><div class="metric-card ai-metric"><span class="ai-icon"><i class="mdi mdi-cash-multiple"></i></span><small>Total spend</small><strong data-ai-metric="spend">${{ number_format($localSpend, 2) }}</strong><p>Live gateway spend for this scope</p></div></div>
        <div class="col-sm-6 col-xl-3"><div class="metric-card ai-metric"><span class="ai-icon"><i class="mdi mdi-wallet-outline"></i></span><small>Total budget</small><strong>${{ number_format($totalBudget, 2) }}</strong><p>Allocated virtual-key budget</p></div></div>
        <div class="col-sm-6 col-xl-3"><div class="metric-card ai-metric"><span class="ai-icon"><i class="mdi mdi-speedometer"></i></span><small>Budget usage</small><strong data-ai-budget-percent>{{ number_format($budgetUsage, 1) }}%</strong><div class="progress"><div class="progress-bar" data-ai-budget-bar style="width: {{ $budgetUsage }}%"></div></div><p data-ai-budget-caption>${{ number_format($localSpend, 2) }} of ${{ number_format($totalBudget, 2) }}</p></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-4"><div class="card platform-card h-100"><div class="card-header platform-card-header"><div><strong>Gateway health</strong><small>Live AI gateway connection</small></div><span class="connection-status is-{{ $gateway?->health_status ?? 'unknown' }}">{{ str($gateway?->health_status ?? 'not configured')->replace('_', ' ')->title() }}</span></div><div class="card-body p-4"><div class="gateway-summary"><div><small>State</small><strong>{{ str($gateway?->health_status ?? 'unknown')->title() }}</strong></div><div><small>Last checked</small><strong>{{ $gateway?->last_checked_at?->diffForHumans() ?? 'Never' }}</strong></div><div><small>Managed keys</small><strong>{{ $credentials->count() }}</strong></div></div><code class="gateway-endpoint">{{ $gateway?->base_url ?: 'Gateway endpoint not configured' }}</code></div></div></div>
        <div class="col-xl-8"><div class="card platform-card h-100"><div class="card-header platform-card-header"><div><strong>Live monitoring</strong><small data-ai-updated>Connecting to LiteLLM…</small></div><span class="live-indicator"><i></i>Auto-refresh</span></div><div class="card-body p-4"><div class="live-metrics"><div><small>Requests</small><strong data-ai-metric="requests">—</strong></div><div><small>Total tokens</small><strong data-ai-metric="total_tokens">—</strong></div><div><small>Input tokens</small><strong data-ai-metric="input_tokens">—</strong></div><div><small>Output tokens</small><strong data-ai-metric="output_tokens">—</strong></div><div><small>Models used</small><strong data-ai-metric="models">—</strong></div><div><small>Success</small><strong data-ai-metric="success_rate">—</strong></div></div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8"><div class="card platform-card h-100"><div class="card-header platform-card-header"><div><strong>Usage trend</strong><small>Daily spend and request volume</small></div></div><div class="card-body p-4"><div class="usage-trend" data-ai-trend><div class="empty-state compact">Loading usage trend…</div></div></div></div></div>
        <div class="col-xl-4"><div class="card platform-card h-100"><div class="card-header platform-card-header"><div><strong>Model distribution</strong><small>Traffic grouped by model</small></div></div><div class="card-body p-4"><div class="model-distribution" data-ai-models><div class="empty-state compact">Loading model activity…</div></div></div></div></div>
    </div>

    @if($credentials->isEmpty())
        <div class="card platform-card mb-4"><div class="empty-state py-5"><i class="mdi mdi-key-outline"></i><strong>No AI access has been provisioned</strong><span>Submit an AI Token request to start using the managed gateway.</span><a href="{{ route('v1.requests.create', ['type' => 'ai_token']) }}" class="btn btn-primary btn-sm mt-2">Request AI token</a></div></div>
    @else
        <div class="card platform-card mb-4"><div class="card-header platform-card-header"><div><strong>{{ $isOrganizationScope ? 'Managed virtual keys' : 'My virtual keys' }}</strong><small>Local correlation only; limits and spend remain governed by LiteLLM.</small></div><span class="badge bg-primary-subtle text-primary">{{ $activeCredentials->count() }} active</span></div><div class="table-responsive"><table class="table platform-table mb-0"><thead><tr><th>Alias / owner</th><th>Token</th><th>Models</th><th>Spend / budget</th><th>Limits</th><th>Status</th></tr></thead><tbody>@foreach($credentials as $credential)@php($keyUsage = (float) $credential->max_budget > 0 ? min(100, ((float) $credential->current_spend / (float) $credential->max_budget) * 100) : 0)<tr><td><strong>{{ $credential->key_alias }}</strong><small>{{ $credential->user?->name ?? auth()->user()->name }} · {{ $credential->request?->code }}</small></td><td><code>{{ $credential->key_preview }}</code></td><td>{{ implode(', ', $credential->models ?? []) ?: 'Gateway default' }}</td><td><strong>${{ number_format((float) $credential->current_spend, 4) }} / ${{ number_format((float) $credential->max_budget, 2) }}</strong><div class="progress key-budget-progress"><div class="progress-bar" style="width: {{ $keyUsage }}%"></div></div></td><td>{{ $credential->rpm_limit ? number_format($credential->rpm_limit).' RPM' : 'No RPM limit' }}<small>{{ $credential->tpm_limit ? number_format($credential->tpm_limit).' TPM' : 'No TPM limit' }}</small></td><td><span class="badge bg-{{ $credential->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $credential->status === 'active' ? 'success' : 'secondary' }}">{{ str($credential->status)->title() }}</span></td></tr>@endforeach</tbody></table></div></div>
    @endif

    <div class="card platform-card"><div class="card-header platform-card-header"><div><strong>Recent AI traffic</strong><small>Latest scoped requests reported by the gateway</small></div><span class="text-muted small" data-ai-log-count>—</span></div><div class="table-responsive"><table class="table platform-table mb-0"><thead><tr><th>Time</th><th>Model</th><th>Request</th><th>Tokens</th><th>Spend</th><th>Status</th></tr></thead><tbody data-ai-logs><tr><td colspan="6" class="text-center text-muted py-4">Loading live data…</td></tr></tbody></table></div></div>
</div>
@endsection
