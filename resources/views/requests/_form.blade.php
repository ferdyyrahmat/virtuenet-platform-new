@php
    $editing = isset($serviceRequest);
    $selectedType = old('type', $editing ? $serviceRequest->type->value : $type?->value);
    $details = old('details', $editing ? $serviceRequest->details : []);
@endphp

<form method="POST" action="{{ $editing ? route('v1.requests.resubmit', $serviceRequest) : route('v1.requests.store') }}" class="platform-request-form">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-md-5">
            <label class="form-label" for="request-type">Request type</label>
            <select id="request-type" name="type" class="form-select" {{ $editing ? 'disabled' : '' }} required>
                <option value="">Choose a service</option>
                @foreach(\App\Enums\ServiceRequestType::cases() as $requestType)
                    <option value="{{ $requestType->value }}" @selected($selectedType === $requestType->value)>{{ $requestType->label() }}</option>
                @endforeach
            </select>
            @if($editing)<input type="hidden" name="type" value="{{ $serviceRequest->type->value }}">@endif
        </div>
        <div class="col-md-7">
            <label class="form-label" for="request-title">Request title</label>
            <input id="request-title" name="title" class="form-control" maxlength="160" value="{{ old('title', $serviceRequest->title ?? '') }}" placeholder="A short, recognizable title" required>
        </div>
        <div class="col-12">
            <label class="form-label" for="request-description">Context and expected outcome</label>
            <textarea id="request-description" name="description" class="form-control" rows="4" maxlength="10000" placeholder="Explain what you need, why it matters, and what success looks like." required>{{ old('description', $serviceRequest->description ?? '') }}</textarea>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="request-priority">Priority</label>
            <select id="request-priority" name="priority" class="form-select" required>
                @foreach(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('priority', $serviceRequest->priority ?? 'normal') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="request-due">Needed by <span class="text-muted">(optional)</span></label>
            <input id="request-due" type="date" name="requested_due_date" class="form-control" min="{{ now()->toDateString() }}" value="{{ old('requested_due_date', isset($serviceRequest) ? $serviceRequest->requested_due_date?->format('Y-m-d') : '') }}">
        </div>
        <div class="col-md-3"><label class="form-label" for="request-budget">Estimated total <span class="text-muted">(required for SaaS)</span></label><input id="request-budget" type="number" name="estimated_budget" class="form-control" min="0" step="0.01" value="{{ old('estimated_budget', $serviceRequest->estimated_budget ?? '') }}"></div>
        <div class="col-md-3"><label class="form-label" for="request-currency">Currency</label><input id="request-currency" name="currency" class="form-control text-uppercase" maxlength="3" value="{{ old('currency', $serviceRequest->currency ?? 'USD') }}" required></div>
    </div>

    <div class="request-detail-panel mt-4" data-request-section="ai_token">
        <h6>AI access requirements</h6>
        <div class="row g-3">
            <div class="col-12"><label class="form-label" for="ai-purpose">Purpose</label><textarea id="ai-purpose" name="details[purpose]" class="form-control" rows="3">{{ data_get($details, 'purpose') }}</textarea></div>
            <div class="col-md-6"><label class="form-label" for="ai-models">Allowed model IDs</label><input id="ai-models" name="details[models]" class="form-control" value="{{ implode(', ', data_get($details, 'models', [])) }}" placeholder="model-a, model-b"><div class="form-text">Use the model IDs published by your connected gateway, separated by commas.</div></div>
            <div class="col-md-3"><label class="form-label" for="ai-budget">Max budget (USD)</label><input id="ai-budget" type="number" step="0.01" min="0.01" name="details[max_budget]" class="form-control" value="{{ data_get($details, 'max_budget') }}"></div>
            <div class="col-md-3"><label class="form-label" for="ai-period">Budget period</label><select id="ai-period" name="details[budget_duration]" class="form-select">@foreach(['1d'=>'Daily','7d'=>'Weekly','30d'=>'30 days','monthly'=>'Monthly'] as $value=>$label)<option value="{{ $value }}" @selected(data_get($details, 'budget_duration', 'monthly') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label" for="ai-rpm">Requests/minute</label><input id="ai-rpm" type="number" min="1" name="details[rpm_limit]" class="form-control" value="{{ data_get($details, 'rpm_limit') }}"></div>
            <div class="col-md-3"><label class="form-label" for="ai-tpm">Tokens/minute</label><input id="ai-tpm" type="number" min="1" name="details[tpm_limit]" class="form-control" value="{{ data_get($details, 'tpm_limit') }}"></div>
        </div>
    </div>

    <div class="request-detail-panel mt-4" data-request-section="custom_system">
        <h6>System scope</h6>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="system-problem">Problem to solve</label><textarea id="system-problem" name="details[problem]" class="form-control" rows="3">{{ data_get($details, 'problem') }}</textarea></div>
            <div class="col-md-6"><label class="form-label" for="system-users">Target users</label><textarea id="system-users" name="details[target_users]" class="form-control" rows="3">{{ data_get($details, 'target_users') }}</textarea></div>
            <div class="col-12"><label class="form-label" for="system-capabilities">Required capabilities</label><textarea id="system-capabilities" name="details[capabilities]" class="form-control" rows="4">{{ data_get($details, 'capabilities') }}</textarea></div>
            <div class="col-12"><div class="form-check form-switch"><input type="hidden" name="details[needs_ai_analyzer]" value="0"><input class="form-check-input" type="checkbox" id="needs-ai" name="details[needs_ai_analyzer]" value="1" @checked(data_get($details, 'needs_ai_analyzer'))><label class="form-check-label" for="needs-ai">This system needs an AI Analyzer token</label></div></div>
            <div class="col-md-6"><label class="form-label" for="system-ai-models">AI model IDs <span class="text-muted">(when enabled)</span></label><input id="system-ai-models" name="details[ai_models]" class="form-control" value="{{ implode(', ', data_get($details, 'ai_models', [])) }}" placeholder="model-a, model-b"></div>
            <div class="col-md-3"><label class="form-label" for="system-ai-budget">AI max budget (USD)</label><input id="system-ai-budget" type="number" step="0.01" min="0.01" name="details[ai_max_budget]" class="form-control" value="{{ data_get($details, 'ai_max_budget') }}"></div>
        </div>
    </div>

    <div class="request-detail-panel mt-4" data-request-section="integration">
        <h6>Integration scope</h6>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="integration-source">Source system</label><input id="integration-source" name="details[source_system]" class="form-control" value="{{ data_get($details, 'source_system') }}"></div>
            <div class="col-md-6"><label class="form-label" for="integration-target">Target system</label><input id="integration-target" name="details[target_system]" class="form-control" value="{{ data_get($details, 'target_system') }}"></div>
            <div class="col-12"><label class="form-label" for="integration-scope">Data and workflow scope</label><textarea id="integration-scope" name="details[scope]" class="form-control" rows="4">{{ data_get($details, 'scope') }}</textarea></div>
            <div class="col-md-6"><label class="form-label" for="integration-access">API / credential readiness</label><select id="integration-access" name="details[access_status]" class="form-select">@foreach(['available'=>'Available','partial'=>'Partially available','not_available'=>'Not available','unknown'=>'Unknown'] as $value=>$label)<option value="{{ $value }}" @selected(data_get($details, 'access_status') === $value)>{{ $label }}</option>@endforeach</select></div>
        </div>
    </div>

    <div class="request-detail-panel mt-4" data-request-section="saas_subscription">
        <h6>Subscription details</h6>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="saas-product">Product</label><input id="saas-product" name="details[product]" class="form-control" value="{{ data_get($details, 'product') }}"></div>
            <div class="col-md-4"><label class="form-label" for="saas-plan">Plan</label><input id="saas-plan" name="details[plan]" class="form-control" value="{{ data_get($details, 'plan') }}"></div>
            <div class="col-md-4"><label class="form-label" for="saas-seats">Seats</label><input id="saas-seats" type="number" min="1" name="details[seats]" class="form-control" value="{{ data_get($details, 'seats', 1) }}"></div>
            <div class="col-md-4"><label class="form-label" for="saas-cycle">Billing cycle</label><select id="saas-cycle" name="details[billing_cycle]" class="form-select">@foreach(['monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly','one_time'=>'One-time'] as $value=>$label)<option value="{{ $value }}" @selected(data_get($details, 'billing_cycle') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-8"><label class="form-label" for="saas-vendor">Vendor URL <span class="text-muted">(optional)</span></label><input id="saas-vendor" type="url" name="details[vendor_url]" class="form-control" value="{{ data_get($details, 'vendor_url') }}"></div>
            <div class="col-12"><label class="form-label" for="saas-reason">Business reason</label><textarea id="saas-reason" name="details[business_reason]" class="form-control" rows="3">{{ data_get($details, 'business_reason') }}</textarea></div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="{{ $editing ? route('v1.requests.show', $serviceRequest) : route('v1.requests.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary px-4">{{ $editing ? 'Submit revision' : 'Submit request' }}</button>
    </div>
</form>
