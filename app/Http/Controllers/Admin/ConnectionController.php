<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalConnection;
use App\Services\GithubLarkSyncService;
use App\Services\GithubTaskService;
use App\Services\LarkService;
use App\Services\LiteLlmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConnectionController extends Controller
{
    public function index(): View
    {
        return view('admin.connections.index', [
            'connections' => ExternalConnection::all()->keyBy('provider'),
            'larkEnvConfigured' => filled(config('services.lark.app_id')) && filled(config('services.lark.app_secret')),
        ]);
    }

    public function update(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['litellm', 'github', 'github_lark_sync', 'lark'], true), 404);
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'base_url' => ['nullable', 'url:http,https', 'max:2048'],
            'enabled' => ['nullable', 'boolean'],
            'secret' => ['nullable', 'string', 'max:10000'],
            'secondary_secret' => ['nullable', 'string', 'max:10000'],
            'repository' => ['nullable', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $connection = ExternalConnection::firstOrNew(['provider' => $provider]);
        $credentials = $connection->credentials ?? [];
        if (filled($validated['secret'] ?? null)) {
            $credentials[match ($provider) {
                'litellm' => 'master_key',
                'lark' => 'app_id',
                'github_lark_sync' => 'api_key',
                default => 'token',
            }] = $validated['secret'];
        }
        if ($provider === 'lark' && filled($validated['secondary_secret'] ?? null)) {
            $credentials['app_secret'] = $validated['secondary_secret'];
        }
        if ($provider === 'github' && filled($validated['webhook_secret'] ?? null)) {
            $credentials['webhook_secret'] = $validated['webhook_secret'];
        }

        $connection->fill([
            'label' => $validated['label'],
            'base_url' => $validated['base_url'] ?? ($provider === 'lark' ? config('services.lark.open_api_url') : null),
            'enabled' => $request->boolean('enabled'),
            'credentials' => $credentials,
            'settings' => $provider === 'github' ? ['repository' => $validated['repository'] ?? null] : ($connection->settings ?? []),
            'health_status' => 'unknown',
            'last_error' => null,
        ])->save();

        activity('integration')
            ->causedBy($request->user())
            ->performedOn($connection)
            ->event('updated')
            ->withProperties(['provider' => $provider, 'enabled' => $connection->enabled, 'base_url' => $connection->base_url])
            ->log(ucfirst($provider).' gateway connection updated.');

        return response()->json(['success' => true, 'message' => ucfirst($provider).' connection saved securely.', 'redirect' => route('admin.connections.index')]);
    }

    public function test(string $provider, LiteLlmService $liteLlm, GithubTaskService $github, GithubLarkSyncService $githubLarkSync, LarkService $lark): JsonResponse
    {
        $connection = ExternalConnection::where('provider', $provider)->firstOrFail();

        try {
            match ($provider) {
                'litellm' => $liteLlm->health(),
                'github' => $github->issues(),
                'github_lark_sync' => $githubLarkSync->health(),
                'lark' => $lark->tenantAccessToken(),
            };
            $connection->update(['health_status' => 'healthy', 'last_error' => null, 'last_checked_at' => now()]);

            return response()->json(['success' => true, 'message' => 'Connection is healthy.', 'redirect' => route('admin.connections.index')]);
        } catch (\Throwable $exception) {
            $connection->update(['health_status' => 'unhealthy', 'last_error' => $exception->getMessage(), 'last_checked_at' => now()]);

            return response()->json(['success' => false, 'message' => 'Connection failed: '.$exception->getMessage(), 'redirect' => null], 422);
        }
    }
}
