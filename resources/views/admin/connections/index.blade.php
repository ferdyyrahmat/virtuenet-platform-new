@extends('layouts.vertical', ['title' => 'Gateway Connections'])

@section('content')
<div class="container-fluid platform-page">
    <div class="platform-heading"><div><span class="eyebrow">Portable integration layer</span><h4>Gateway connections</h4><p>Keep external product functions external. VirtueNet stores only encrypted credentials and renders their data.</p></div></div>
    <div class="row g-4">
        @foreach([
            'litellm'=>['LiteLLM Gateway','Endpoint and master key for token provisioning, spend, usage, and logs.','Endpoint URL','Master key'],
            'github'=>['GitHub Task Source','Repository issues synced into the platform and delivered as Lark tasks.','Not used','Personal access token'],
            'github_lark_sync'=>['GitHub–Lark Sync','Portable sync service that owns issue-to-task delivery and mirrors mappings into PostgreSQL.','Service endpoint','Optional API key'],
            'coolify'=>['Coolify Inventory','Read-only application inventory sync for runtime, domain, branch, and deployment identifiers.','Coolify URL','Read-only API token'],
            'lark'=>['Lark Workspace','SSO, messages, approvals, and task delivery through one app connection.','Open API URL','App ID'],
        ] as $provider=>$meta)
            @php($connection=$connections->get($provider))
            <div class="col-xl-3 col-md-6"><div class="card platform-card h-100"><div class="card-body p-4">
                <div class="connection-header"><span class="connection-icon"><i class="mdi {{ match($provider){'litellm'=>'mdi-creation','github'=>'mdi-github','github_lark_sync'=>'mdi-source-branch-sync','coolify'=>'mdi-cloud-sync-outline',default=>'mdi-message-processing-outline'} }}"></i></span><div><h6>{{ $meta[0] }}</h6><span class="connection-status is-{{ $connection?->health_status ?? 'unknown' }}">{{ str($connection?->health_status ?? 'not configured')->replace('_',' ')->title() }}</span></div></div><p class="text-muted small my-3">{{ $meta[1] }}</p>
                <form method="POST" action="{{ route('admin.connections.update',$provider) }}">@csrf @method('PUT')<input type="hidden" name="label" value="{{ $meta[0] }}"><div class="form-check form-switch mb-3"><input type="hidden" name="enabled" value="0"><input class="form-check-input" type="checkbox" id="enabled-{{ $provider }}" name="enabled" value="1" @checked($connection?->enabled)><label class="form-check-label" for="enabled-{{ $provider }}">Enable connection</label></div>
                    @if($provider!=='github')<label class="form-label">{{ $meta[2] }}</label><input type="url" name="base_url" class="form-control" value="{{ $connection?->base_url ?? ($provider==='lark'?config('services.lark.open_api_url'):'') }}" placeholder="https://…">@endif
                    @if($provider==='github')<label class="form-label">Repository</label><input name="repository" class="form-control" value="{{ data_get($connection?->settings,'repository') }}" placeholder="organization/github-lark-sync">@endif
                    <label class="form-label mt-3">{{ $meta[3] }}</label><input type="password" name="secret" class="form-control" autocomplete="new-password" placeholder="{{ $connection && data_get($connection->credentials,match($provider){'litellm'=>'master_key','lark'=>'app_id','github_lark_sync'=>'api_key',default=>'token'}) ? 'Saved — leave blank to keep' : ($provider==='github_lark_sync'?'Leave blank when internal':'Enter credential') }}">
                    @if($provider==='lark')<label class="form-label mt-3">App secret</label><input type="password" name="secondary_secret" class="form-control" autocomplete="new-password" placeholder="{{ $connection && data_get($connection->credentials,'app_secret') ? 'Saved — leave blank to keep' : 'Enter app secret' }}">@endif
                    @if($provider==='github')<label class="form-label mt-3">Webhook secret</label><input type="password" name="webhook_secret" class="form-control" autocomplete="new-password" placeholder="Saved securely"><div class="form-text">Webhook URL: {{ route('webhooks.github') }}</div>@endif
                    <button class="btn btn-primary w-100 mt-4">Save connection</button>
                </form>
                @if($connection)<form method="POST" action="{{ route('admin.connections.test',$provider) }}" class="mt-2">@csrf<button class="btn btn-light w-100">Test connection</button></form>@endif
                @if($connection?->last_error)<div class="alert alert-warning small mt-3 mb-0">{{ str($connection->last_error)->limit(180) }}</div>@endif
            </div></div></div>
        @endforeach
    </div>
</div>
@endsection
